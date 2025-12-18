<?php
// reports/get_report.php - Fetch report data for AJAX edit overlay

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

header('Content-Type: application/json');

$report_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($report_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid report ID.']);
    exit;
}

// Fetch report data
$stmt = $conn->prepare("SELECT * FROM project_reports WHERE id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    echo json_encode(['success' => false, 'message' => 'Report not found.']);
    exit;
}

// Format date for display (MM-DD-YYYY)
$report_date_formatted = '';
if ($report['report_date'] && $report['report_date'] !== '0000-00-00') {
    $report_date_formatted = date('m-d-Y', strtotime($report['report_date']));
}

// Fetch materials used in this report
$materials_used = [];
$mat_stmt = $conn->prepare("
    SELECT rmu.material_id, rmu.quantity_used, m.name, m.unit_of_measurement
    FROM report_material_usage rmu
    JOIN materials m ON m.id = rmu.material_id
    WHERE rmu.report_id = ?
");
$mat_stmt->bind_param("i", $report_id);
$mat_stmt->execute();
$mat_result = $mat_stmt->get_result();
while ($row = $mat_result->fetch_assoc()) {
    $materials_used[] = $row;
}
$mat_stmt->close();

// Fetch images for this report
$images = [];
$img_stmt = $conn->prepare("SELECT id, image_path FROM report_images WHERE report_id = ?");
$img_stmt->bind_param("i", $report_id);
$img_stmt->execute();
$img_result = $img_stmt->get_result();
while ($row = $img_result->fetch_assoc()) {
    $images[] = $row;
}
$img_stmt->close();

echo json_encode([
    'success' => true,
    'report' => [
        'id' => $report['id'],
        'project_id' => $report['project_id'],
        'unit_id' => $report['unit_id'],
        'report_date' => $report_date_formatted,
        'progress_percentage' => (int)$report['progress_percentage'],
        'work_done' => $report['work_done'],
        'remarks' => $report['remarks'] ?? '',
        'materials_used' => $materials_used,
        'images' => $images
    ]
]);
