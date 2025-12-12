<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request.');
}

$id = intval($_POST['project_id'] ?? 0);
$name = trim($_POST['project_name'] ?? '');
$location = trim($_POST['project_location'] ?? '');
$units = intval($_POST['units'] ?? 0);
$status = trim($_POST['status'] ?? '');

if ($id <= 0 || empty($name) || empty($location) || $units <= 0 || empty($status)) {
    exit('⚠️ Please fill in all required fields.');
}

// Update project details
$stmt = $conn->prepare("UPDATE projects SET name=?, location=?, units=?, status=? WHERE id=?");
$stmt->bind_param("ssisi", $name, $location, $units, $status, $id);
$stmt->execute();
$stmt->close();

// Log the edit action
log_activity($conn, 'EDIT_PROJECT', "Edited project: $name (ID: $id) - Location: $location, Units: $units, Status: $status");

// Adjust units dynamically
$res = $conn->prepare("SELECT COUNT(*) FROM project_units WHERE project_id=?");
$res->bind_param("i", $id);
$res->execute();
$res->bind_result($current_units);
$res->fetch();
$res->close();

if ($units > $current_units) {
    // Add missing units
    $insert = $conn->prepare("INSERT INTO project_units (project_id, name, description, progress, created_at) VALUES (?, ?, '', 0, NOW())");
    for ($i = $current_units + 1; $i <= $units; $i++) {
        $unit_name = "Unit " . $i;
        $insert->bind_param("is", $id, $unit_name);
        $insert->execute();
    }
    $insert->close();
} elseif ($units < $current_units) {
    // Remove extra units
    $extra = $current_units - $units;
    $delete = $conn->prepare("DELETE FROM project_units WHERE project_id=? ORDER BY id DESC LIMIT ?");
    $delete->bind_param("ii", $id, $extra);
    $delete->execute();
    $delete->close();
}

exit('✅ Project updated successfully.');
?>
