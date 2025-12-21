
<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    die(json_encode(['status' => 'error', 'message' => 'Invalid checklist ID']));
}

$checklist_id = intval($_GET['id']);
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;

// Get checklist item name for logging
$item_name = '';

// Use the correct column name: item_description
$stmt_get = $conn->prepare("SELECT item_description FROM project_checklists WHERE id = ?");
if ($stmt_get === false) {
    header('Content-Type: application/json');
    die(json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]));
}
$stmt_get->bind_param("i", $checklist_id);
$stmt_get->execute();
$result = $stmt_get->get_result();
if ($row = $result->fetch_assoc()) {
    $item_name = $row['item_description'];
}
$stmt_get->close();

// ✅ Securely delete the checklist item
$stmt = $conn->prepare("DELETE FROM project_checklists WHERE id = ?");
if ($stmt === false) {
    header('Content-Type: application/json');
    die(json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]));
}
$stmt->bind_param("i", $checklist_id);

if ($stmt->execute()) {
    $stmt->close();
    // Log the delete action
    log_activity($conn, 'DELETE_CHECKLIST_ITEM', "Deleted checklist item: $item_name (ID: $checklist_id) from project ID: $project_id");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
} else {
    $errorMsg = $stmt->error;
    $stmt->close();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete: ' . $errorMsg]);
    exit;
}
