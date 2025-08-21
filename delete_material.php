<?php
// delete material 
include 'includes/db.php';
include 'includes/auth.php';

if (!is_admin()) {
    header("Location: supply_monitoring.php");
    exit();
}
$id = intval($_GET['id']);
$conn->query("DELETE FROM materials WHERE id=$id");
header("Location: supply_monitoring.php");
exit();
?>