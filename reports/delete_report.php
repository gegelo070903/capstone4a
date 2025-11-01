<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if ($report_id <= 0 || $project_id <= 0) {
    die('Invalid request.');
}

try {
    $conn->begin_transaction();

    // Fetch material usage so we can restore quantities
    $usage_stmt = $conn->prepare("SELECT material_id, quantity_used FROM report_material_usage WHERE report_id = ?");
    $usage_stmt->bind_param("i", $report_id);
    $usage_stmt->execute();
    $usage_res = $usage_stmt->get_result();

    while ($row = $usage_res->fetch_assoc()) {
        $material_id = (int)$row['material_id'];
        $qty_used = (int)$row['quantity_used'];

        // Restore material quantity
        $conn->query("UPDATE materials SET remaining_quantity = remaining_quantity + $qty_used WHERE id = $material_id");
    }

    // Delete material usage records
    $conn->query("DELETE FROM report_material_usage WHERE report_id = $report_id");

    // Delete image files and database entries
    $img_stmt = $conn->prepare("SELECT image_path FROM report_images WHERE report_id = ?");
    $img_stmt->bind_param("i", $report_id);
    $img_stmt->execute();
    $img_res = $img_stmt->get_result();

    while ($img = $img_res->fetch_assoc()) {
        $path = __DIR__ . '/report_images/' . $img['image_path'];
        if (file_exists($path)) unlink($path);
    }

    $conn->query("DELETE FROM report_images WHERE report_id = $report_id");

    // Finally, delete the report itself
    $conn->query("DELETE FROM project_reports WHERE id = $report_id");

    $conn->commit();

    header("Location: ../modules/view_project.php?id=$project_id&tab=reports");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    echo "<p style='color:red;'>Error deleting report: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
