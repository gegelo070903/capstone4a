<?php
// ===============================================================
// modules/delete_project.php — Safe Soft Delete Version
// ===============================================================

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo "<h3 style='color:red'>❌ Invalid project ID.</h3>";
    exit();
}

// Get project name for logging
$project_name = '';
$stmt_get = $conn->prepare("SELECT name FROM projects WHERE id = ?");
$stmt_get->bind_param("i", $id);
$stmt_get->execute();
$result = $stmt_get->get_result();
if ($row = $result->fetch_assoc()) {
    $project_name = $row['name'];
}
$stmt_get->close();

// 🧱 Ensure soft-delete column exists
// (Run this once manually in phpMyAdmin or migration SQL)
# ALTER TABLE projects ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER status;
# ALTER TABLE projects ADD COLUMN deleted_at DATETIME NULL AFTER is_deleted;

// ✅ Soft delete: mark as deleted instead of removing
$stmt = $conn->prepare("UPDATE projects SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Log the archive action
    log_activity($conn, 'ARCHIVE_PROJECT', "Archived project: $project_name (ID: $id)");
    header("Location: ../uploads/projects.php?status=success&message=" . urlencode("Project archived successfully."));
    exit();
} else {
    echo "<h3 style='color:red'> Soft delete failed: " . htmlspecialchars($stmt->error) . "</h3>";
}
$stmt->close();
?>
