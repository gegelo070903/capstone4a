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

// 🧱 Ensure soft-delete column exists
// (Run this once manually in phpMyAdmin or migration SQL)
# ALTER TABLE projects ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER status;
# ALTER TABLE projects ADD COLUMN deleted_at DATETIME NULL AFTER is_deleted;

// ✅ Soft delete: mark as deleted instead of removing
$stmt = $conn->prepare("UPDATE projects SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: ../uploads/projects.php?status=success&message=" . urlencode("🗑️ Project archived (soft deleted) successfully."));
    exit();
} else {
    echo "<h3 style='color:red'>❌ Soft delete failed: " . htmlspecialchars($stmt->error) . "</h3>";
}
$stmt->close();
?>
