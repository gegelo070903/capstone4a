<?php
// add_development_report.php - Pure processing script for daily reports

// --- ALL PHP LOGIC MUST COME BEFORE ANY HTML OUTPUT (for headers to work) ---

session_start(); // Ensure session is started for auth check
include 'includes/db.php';
include 'includes/functions.php'; // For is_admin() and session checks

date_default_timezone_set('Asia/Manila');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Ensure the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: projects.php?status=report_added_error&message=" . urlencode("Invalid access method."));
    exit();
}

// --- Get project_id from hidden form field ---
if (!isset($_POST['project_id']) || empty($_POST['project_id'])) {
    header("Location: projects.php?status=report_added_error&message=" . urlencode("Project ID not provided."));
    exit();
}
$project_id_from_form = intval($_POST['project_id']);
$constructor_id = $_SESSION['user_id']; // The user submitting the report

// --- Security Check (duplicated from view_project.php for robustness) ---
$stmt_project = $conn->prepare("SELECT constructor_id FROM projects WHERE id = ?");
$stmt_project->bind_param("i", $project_id_from_form);
$stmt_project->execute();
$project_result = $stmt_project->get_result();
$project = $project_result->fetch_assoc();
$stmt_project->close();

if (!$project) {
    header("Location: projects.php?status=report_added_error&message=" . urlencode("Project not found during report submission."));
    exit();
}

$is_assigned_constructor = ($constructor_id == $project['constructor_id']);

if (!is_admin() && !$is_assigned_constructor) {
    header("Location: view_project.php?id=" . $project_id_from_form . "&status=report_added_error&message=" . urlencode("You are not authorized to add reports to this project."));
    exit();
}

// --- Initialize Variables ---
$report_date = $_POST['report_date'];
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];
$status = $_POST['status'];
$description = trim($_POST['description']);
// $materials_left is REMOVED as it's replaced by dynamic material usage
$proof_image_path = NULL;
$error_message = "";

// --- Validate essential fields ---
if (empty($report_date) || empty($start_time) || empty($end_time) || empty($status) || empty($description)) {
    $error_message = "Please fill in all required fields (Date, Time, Status, Description).";
}

// --- File Upload Handling ---
if (empty($error_message) && isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
    $file_tmp_name = $_FILES['proof_image']['tmp_name'];
    $file_name = $_FILES['proof_image']['name'];
    $file_size = $_FILES['proof_image']['size'];
    $file_type = $_FILES['proof_image']['type'];

    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');

    if (!in_array($file_ext, $allowed_extensions)) {
        $error_message = "Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.";
    } elseif ($file_size > 5 * 1024 * 1024) { // 5MB max size
        $error_message = "File is too large. Max 5MB allowed.";
    } else {
        $upload_dir = 'uploads/reports_proofs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $new_file_name = uniqid('proof_', true) . '.' . $file_ext;
        $destination = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp_name, $destination)) {
            $proof_image_path = $destination;
        } else {
            $error_message = "Failed to upload image.";
        }
    }
}
// --- End File Upload Handling ---


// --- If no error, proceed with DB insertion using a transaction ---
if (empty($error_message)) {
    $conn->begin_transaction();
    try {
        // 1. Insert into construction_reports table
        // Removed `materials_left` column from here
        $stmt_report = $conn->prepare("INSERT INTO construction_reports (project_id, constructor_id, report_date, start_time, end_time, status, description, proof_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_report->bind_param("iissssss", $project_id_from_form, $constructor_id, $report_date, $start_time, $end_time, $status, $description, $proof_image_path);

        if (!$stmt_report->execute()) {
            throw new Exception("Error adding daily report: " . $stmt_report->error);
        }
        $report_id = $stmt_report->insert_id; // Get the ID of the newly inserted report
        $stmt_report->close();

        // 2. Process and insert material usage (if any)
        if (isset($_POST['material_ids']) && is_array($_POST['material_ids']) &&
            isset($_POST['quantities_used']) && is_array($_POST['quantities_used'])) {

            $material_ids = $_POST['material_ids'];
            $quantities_used = $_POST['quantities_used'];

            $stmt_material_usage = $conn->prepare("INSERT INTO report_material_usage (report_id, material_id, quantity_used, created_at) VALUES (?, ?, ?, NOW())");

            foreach ($material_ids as $key => $material_id_val) {
                $material_id = intval($material_id_val);
                $quantity_used = floatval($quantities_used[$key]);

                // Only insert if both material_id and quantity_used are valid
                if ($material_id > 0 && $quantity_used > 0) {
                    $stmt_material_usage->bind_param("iid", $report_id, $material_id, $quantity_used);
                    if (!$stmt_material_usage->execute()) {
                        throw new Exception("Error adding material usage for material ID " . $material_id . ": " . $stmt_material_usage->error);
                    }
                }
            }
            $stmt_material_usage->close();
        }

        // If everything is successful, commit the transaction
        $conn->commit();
        header("Location: view_project.php?id=" . $project_id_from_form . "&status=report_added_success");
        exit();

    } catch (Exception $e) {
        $conn->rollback(); // Rollback on any error
        $error_message = $e->getMessage();
    }
}

// If an error occurred (either from validation or transaction), redirect with the message
if (!empty($error_message)) {
    header("Location: view_project.php?id=" . $project_id_from_form . "&status=report_added_error&message=" . urlencode($error_message));
    exit();
}
?>