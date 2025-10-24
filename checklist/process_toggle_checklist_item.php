<?php
// ===============================================================
// process_toggle_checklist_item.php
// Toggle a checklist item's completion status (admin-only).
// Returns JSON: { success: true } or { success: false, message: "..." }.
// ===============================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_csrf();

header('Content-Type: application/json');

// 1) Input
$checklist_id = post_int('checklist_id');
$is_completed = post_int('is_completed'); // 0 or 1

if (!$checklist_id || ($is_completed !== 0 && $is_completed !== 1)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

// 2) Enforce admin (matches UI behavior)
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

// 3) Fetch the checklist item and its project for integrity
$stmt = $conn->prepare('
    SELECT pc.id, pc.project_id
    FROM project_checklists pc
    WHERE pc.id = ?
    LIMIT 1
');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Unexpected error.']);
    exit;
}
$stmt->bind_param('i', $checklist_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Checklist item not found.']);
    exit;
}

// 4) Update status
if ($is_completed === 1) {
    $stmt = $conn->prepare('
        UPDATE project_checklists
        SET is_completed = 1,
            completed_by_user_id = ?,
            completed_at = NOW()
        WHERE id = ?
        LIMIT 1
    ');
    $uid = current_user_id();
    $stmt->bind_param('ii', $uid, $checklist_id);
} else {
    $stmt = $conn->prepare('
        UPDATE project_checklists
        SET is_completed = 0,
            completed_by_user_id = NULL,
            completed_at = NULL
        WHERE id = ?
        LIMIT 1
    ');
    $stmt->bind_param('i', $checklist_id);
}

$ok = $stmt && $stmt->execute();
if ($stmt) $stmt->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
    exit;
}

echo json_encode(['success' => true]);
