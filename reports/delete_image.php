<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: /capstone/users/login.php');
    exit;
}

$image_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$report_id = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;

if ($image_id <= 0 || $report_id <= 0) {
    header("Location: edit_report.php?id=$report_id");
    exit;
}

// Fetch image path before deleting
$stmt = $conn->prepare("SELECT image_path FROM report_images WHERE id = ?");
$stmt->bind_param("i", $image_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($res) {
    $filePath = __DIR__ . '/report_images/' . $res['image_path'];
    if (file_exists($filePath)) {
        unlink($filePath); // Delete file
    }

    // Delete from database
    $del = $conn->prepare("DELETE FROM report_images WHERE id = ?");
    $del->bind_param("i", $image_id);
    $del->execute();
    $del->close();
}

// Redirect back to the same edit page
header("Location: edit_report.php?id=$report_id");
exit;
?>
