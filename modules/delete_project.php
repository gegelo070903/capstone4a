<?php
// ======================================
// delete_project.php — FINAL SOFT DELETE VERSION
// ======================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
if (!is_admin()) {
    die('<h3 style="color:red;">Access denied. Only admins can delete projects.</h3>');
}

// ✅ Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('<h3 style="color:red;">Invalid project ID.</h3>');
}
$project_id = intval($_GET['id']);

// ✅ Fetch project
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('<h3 style="color:red;">Project not found.</h3>');
}
$project = $result->fetch_assoc();

// ✅ Ensure deleted_projects table exists
$conn->query("CREATE TABLE IF NOT EXISTS deleted_projects LIKE projects");
$conn->query("
    ALTER TABLE deleted_projects 
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP
");

// ✅ Get column names dynamically
$colRes = $conn->query("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'projects'
");
$projectCols = [];
while ($row = $colRes->fetch_assoc()) {
    $projectCols[] = $row['COLUMN_NAME'];
}

// ✅ Build dynamic query
$validCols = array_intersect($projectCols, array_keys($project));
$cols = implode('`, `', $validCols);
$placeholders = implode(',', array_fill(0, count($validCols), '?'));
$types = '';
$values = [];

foreach ($validCols as $c) {
    $types .= is_int($project[$c]) ? 'i' : 's';
    $values[] = $project[$c];
}

// ✅ Start transaction
$conn->begin_transaction();

try {
    // Insert into deleted_projects
    $insert = $conn->prepare("INSERT INTO deleted_projects (`$cols`) VALUES ($placeholders)");
    $insert->bind_param($types, ...$values);
    $insert->execute();

    // ✅ Handle related tables
    $relatedTables = [
        'project_materials',
        'development_reports',
        'project_units',
        'milestones',
        'supplies',
        'checklist_items'
    ];

    foreach ($relatedTables as $table) {
        $exists = $conn->query("SHOW TABLES LIKE '$table'");
        if ($exists->num_rows === 0) continue;

        $conn->query("CREATE TABLE IF NOT EXISTS deleted_$table LIKE $table");
        $conn->query("
            ALTER TABLE deleted_$table 
            ADD COLUMN IF NOT EXISTS deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ");

        $conn->query("INSERT INTO deleted_$table SELECT * FROM $table WHERE project_id = $project_id");
        $conn->query("DELETE FROM $table WHERE project_id = $project_id");
    }

    // ✅ Finally delete the project
    $del = $conn->prepare("DELETE FROM projects WHERE id = ?");
    $del->bind_param("i", $project_id);
    $del->execute();

    $conn->commit();

    echo "<h3 style='color:orange;'>🗂️ Project moved to trash successfully (soft deleted).</h3>";
    echo "<meta http-equiv='refresh' content='2;url=../uploads/projects.php'>";
    exit();

} catch (Exception $e) {
    $conn->rollback();
    die('<h3 style="color:red;">❌ Soft delete failed: ' . htmlspecialchars($e->getMessage()) . '</h3>');
}
?>