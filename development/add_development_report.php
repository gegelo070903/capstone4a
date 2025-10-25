<?php
// ===============================================================
// add_development_report.php
// Securely handles adding a daily development report
// with optional proof image and material usage.
// ===============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../auth/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_login();

date_default_timezone_set('Asia/Manila');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit();
}

// ---------------------------------------------------------------
// Collect and validate inputs
// ---------------------------------------------------------------
$project_id  = (int)($_POST['project_id'] ?? 0);
$report_date = trim($_POST['report_date'] ?? '');
$start_time  = trim($_POST['start_time'] ?? '');
$end_time    = trim($_POST['end_time'] ?? '');
$status      = trim($_POST['status'] ?? '');
$description = trim($_POST['description'] ?? '');

if (!$project_id || !$report_date || !$start_time || !$end_time || !$status) {
    redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Missing required fields.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $report_date)) {
    redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Invalid date format.');
}
if (!preg_match('/^\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
    redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Invalid time format.');
}
if (!in_array($status, ['ongoing', 'complete', 'pending'], true)) {
    redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Invalid project status.');
}

// ---------------------------------------------------------------
// Optional: File upload
// ---------------------------------------------------------------
$proof_image_path = null;

if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($_FILES['proof_image']['size'] > $maxSize) {
            redirect_with_message('../modules/view_project.php?id=' . $project_id, 'File too large. Max 5MB allowed.');
        }

        $ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed)) {
            redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Invalid file type. Only JPG, PNG, WEBP allowed.');
        }

        $uploadDir = __DIR__ . '/../uploads/reports_proofs';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $newName  = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath = $uploadDir . '/' . $newName;

        if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $destPath)) {
            redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Failed to upload proof image.');
        }

        $proof_image_path = 'uploads/reports_proofs/' . $newName;
    } else {
        redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Error uploading image.');
    }
}

// ---------------------------------------------------------------
// Optional: Material usage
// ---------------------------------------------------------------
$material_ids = $_POST['material_id'] ?? [];
$quantities   = $_POST['quantity_used'] ?? [];
$usage = [];

foreach ($material_ids as $i => $mid) {
    $mid = (int)$mid;
    $qty = trim($quantities[$i] ?? '');
    if ($mid > 0 && $qty !== '') {
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $qty)) {
            redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Invalid quantity format.');
        }
        $usage[] = ['material_id' => $mid, 'quantity_used' => (float)$qty];
    }
}

// ---------------------------------------------------------------
// Database transaction
// ---------------------------------------------------------------
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO construction_reports
            (project_id, constructor_id, report_date, start_time, end_time, status, description, proof_image)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $user_id = $_SESSION['user_id'];
    $stmt->bind_param('isssssss',
        $project_id, $user_id, $report_date, $start_time,
        $end_time, $status, $description, $proof_image_path
    );
    $stmt->execute();
    $report_id = $stmt->insert_id;
    $stmt->close();

    // Insert materials usage
    if (!empty($usage)) {
        $stmtU = $conn->prepare("
            INSERT INTO report_material_usage (report_id, material_id, quantity_used, is_deducted, created_at)
            VALUES (?, ?, ?, 0, NOW())
        ");
        foreach ($usage as $row) {
            $stmtU->bind_param('iid', $report_id, $row['material_id'], $row['quantity_used']);
            $stmtU->execute();
        }
        $stmtU->close();
    }

    $conn->commit();
    redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Development report submitted successfully!');

} catch (Throwable $e) {
    $conn->rollback();
    if ($proof_image_path && is_file(__DIR__ . '/../' . $proof_image_path)) {
        @unlink(__DIR__ . '/../' . $proof_image_path);
    }
    error_log('Add Dev Report Failed: ' . $e->getMessage());
    redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Error saving report. Please try again.');
}
?>