<?php
// ======================================================================
// submit_report.php
// Handles creation of new construction (daily) reports by constructors.
// ======================================================================

session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

date_default_timezone_set('Asia/Manila');

// --------------------------------------------------
// Access control
// --------------------------------------------------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'constructor') {
    header("Location: login.php");
    exit();
}

// --------------------------------------------------
// Validate required POST fields
// --------------------------------------------------
$required_fields = ['project_id', 'report_date', 'start_time', 'end_time', 'status', 'description'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $_SESSION['status_message'] = '<div class="alert error">All fields are required.</div>';
        header("Location: add_development_report.php");
        exit();
    }
}

$constructor_id = $_SESSION['user_id'];
$project_id     = intval($_POST['project_id']);
$report_date    = $_POST['report_date'];
$start_time     = $_POST['start_time'];
$end_time       = $_POST['end_time'];
$status         = $_POST['status'];
$description    = trim($_POST['description']);
$proof_image    = null;

// --------------------------------------------------
// Handle proof image upload (optional)
// --------------------------------------------------
if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (in_array($ext, $allowed)) {
        $upload_dir = 'uploads/reports_proofs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $new_filename = uniqid('proof_', true) . '.' . $ext;
        $target_path = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $target_path)) {
            $proof_image = $target_path;
        } else {
            $_SESSION['status_message'] = '<div class="alert error">Failed to upload proof image.</div>';
            header("Location: add_development_report.php");
            exit();
        }
    } else {
        $_SESSION['status_message'] = '<div class="alert error">Invalid image format. Allowed: JPG, PNG, GIF, WEBP.</div>';
        header("Location: add_development_report.php");
        exit();
    }
}

// --------------------------------------------------
// Insert report into database
// --------------------------------------------------
$stmt = $conn->prepare("
    INSERT INTO construction_reports 
        (project_id, constructor_id, report_date, start_time, end_time, status, description, proof_image)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    'iissssss',
    $project_id,
    $constructor_id,
    $report_date,
    $start_time,
    $end_time,
    $status,
    $description,
    $proof_image
);

if ($stmt->execute()) {
    $_SESSION['status_message'] = '<div class="alert success">Daily report submitted successfully!</div>';
    header("Location: development_monitoring.php?status=report_added_success");
    exit();
} else {
    $_SESSION['status_message'] = '<div class="alert error">Error saving report: ' . htmlspecialchars($stmt->error) . '</div>';
    header("Location: add_development_report.php?status=report_added_error");
    exit();
}

$stmt->close();
$conn->close();
exit();
?>
