<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $material_id = intval($_POST['material_id']);
    $project_id = intval($_POST['project_id']);
    $used_qty = floatval($_POST['used_quantity']);

    if ($used_qty <= 0) {
        die('Invalid quantity.');
    }

    // ✅ Get current quantity and name
    $stmt = $conn->prepare("SELECT name, remaining_quantity FROM materials WHERE id = ?");
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) die('Material not found.');
    
    $material_name = $result['name'];

    $remaining = $result['remaining_quantity'] - $used_qty;
    if ($remaining < 0) $remaining = 0;

    // ✅ Update materials table
    $update = $conn->prepare("UPDATE materials SET remaining_quantity = ? WHERE id = ?");
    $update->bind_param("di", $remaining, $material_id);
    $update->execute();

    // ✅ Log the usage
    $log = $conn->prepare("INSERT INTO material_usage_log (material_id, project_id, used_quantity, remarks) VALUES (?, ?, ?, ?)");
    $remarks = trim($_POST['remarks']) ?: 'Used in project';
    $log->bind_param("iids", $material_id, $project_id, $used_qty, $remarks);
    $log->execute();
    
    // Log activity
    log_activity($conn, 'DEDUCT_MATERIAL', "Deducted $used_qty from material: $material_name (ID: $material_id) in project ID: $project_id");

    header("Location: ../modules/view_project.php?id=$project_id&tab=materials");
    exit();
}
?>
