<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

// Validate parameters
if (!isset($_GET['id']) || !isset($_GET['project_id'])) {
    die('<h3 style="color:red;">Invalid parameters provided.</h3>');
}

$id = intval($_GET['id']);
$project_id = intval($_GET['project_id']);

// Delete material immediately
$stmt = $conn->prepare("DELETE FROM materials WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    header("Location: ../modules/view_project.php?id=$project_id");
    exit;
} else {
    echo "<h3 style='color:red;'>Error deleting material. Please try again.</h3>";
}
?>
