<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Invalid checklist ID']));
}

$checklist_id = intval($_GET['id']);
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;

// ✅ Securely delete the checklist item
$stmt = $conn->prepare("DELETE FROM project_checklists WHERE id = ?");
$stmt->bind_param("i", $checklist_id);

if ($stmt->execute()) {
    $stmt->close();
    // ✅ Respond as JSON for fetch()
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
} else {
    $stmt->close();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete']);
    exit;
}
