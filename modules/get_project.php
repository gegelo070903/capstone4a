<?php
// ===============================================================
// modules/get_project.php — Return project data as JSON for overlay edit
// ===============================================================

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

// Validate
if ($id <= 0) {
    echo json_encode(['error' => 'Invalid project ID.']);
    exit;
}

// Fetch project details
$stmt = $conn->prepare("SELECT id, name, location, units, status FROM projects WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    echo json_encode(['error' => 'Project not found.']);
    exit;
}

// Return project data as JSON
echo json_encode($result);
?>
