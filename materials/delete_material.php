<?php
// materials/delete_material.php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

// Validate parameters
if (!isset($_GET['id']) || !isset($_GET['project_id'])) {
    die('<h3 style="color:red;">Invalid parameters provided.</h3>');
}

$id = intval($_GET['id']);
$project_id = intval($_GET['project_id']);

// Get material name for logging
$material_name = '';
$stmt_get = $conn->prepare("SELECT name FROM materials WHERE id = ?");
$stmt_get->bind_param("i", $id);
$stmt_get->execute();
$result = $stmt_get->get_result();
if ($row = $result->fetch_assoc()) {
    $material_name = $row['name'];
}
$stmt_get->close();

// Delete material immediately
$stmt = $conn->prepare("DELETE FROM materials WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    // Log the delete action
    log_activity($conn, 'DELETE_MATERIAL', "Deleted material: $material_name (ID: $id) from project ID: $project_id");
    // 🎯 UPDATED REDIRECT to include &tab=materials
    header("Location: ../modules/view_project.php?id=$project_id&tab=materials");
    exit;
} else {
    echo "<h3 style='color:red;'>Error deleting material. Please try again.</h3>";
}
?>