<?php
// ===============================================================
// delete_development_report.php
// Securely deletes a development report and related material usage.
// Only Admins or the assigned Constructor can perform this action.
// Wrapped in a transaction for safety.
// ===============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../auth/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_login();

date_default_timezone_set('Asia/Manila');

// ---------------------------------------------------------------
// Validate input
// ---------------------------------------------------------------
$report_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($report_id <= 0) {
    redirect_with_message('../modules/development_monitoring.php', 'Invalid report ID.');
}

// ---------------------------------------------------------------
// Fetch report info
// ---------------------------------------------------------------
$stmt = $conn->prepare("
    SELECT cr.id, cr.project_id, cr.constructor_id, cr.proof_image, p.assigned_to AS project_constructor_id
    FROM construction_reports cr
    JOIN projects p ON cr.project_id = p.id
    WHERE cr.id = ?
");
$stmt->bind_param('i', $report_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();
$stmt->close();

if (!$report) {
    redirect_with_message('../modules/development_monitoring.php', 'Report not found.');
}

$project_id = (int)$report['project_id'];
$constructor_id = (int)$report['project_constructor_id'];

// ---------------------------------------------------------------
// Authorization check
// ---------------------------------------------------------------
if (!is_admin() && ($_SESSION['user_id'] ?? 0) !== $constructor_id) {
    redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Unauthorized to delete this report.');
}

// ---------------------------------------------------------------
// Delete with transaction
// ---------------------------------------------------------------
$conn->begin_transaction();

try {
    // 1️⃣ Delete associated material usage first
    $stmt = $conn->prepare("DELETE FROM report_material_usage WHERE report_id = ?");
    $stmt->bind_param('i', $report_id);
    $stmt->execute();
    $stmt->close();

    // 2️⃣ Delete the report itself
    $stmt = $conn->prepare("DELETE FROM construction_reports WHERE id = ?");
    $stmt->bind_param('i', $report_id);
    $stmt->execute();
    $stmt->close();

    // 3️⃣ Delete the proof image if it exists
    if (!empty($report['proof_image'])) {
        $image_path = __DIR__ . '/../' . $report['proof_image'];
        if (is_file($image_path)) {
            @unlink($image_path);
        }
    }

    $conn->commit();
    redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Development report and related materials deleted successfully!');

} catch (Throwable $e) {
    $conn->rollback();
    error_log('Delete Dev Report Error: ' . $e->getMessage());
    redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Error deleting report. Please try again.');
}
?>
