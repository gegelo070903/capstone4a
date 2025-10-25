<?php
// ===============================================================
// process_toggle_checklist_item.php
// Toggle a checklist item's completion status (admin-only).
// Returns JSON: { success: true } or { success: false, message: "..." }.
// ===============================================================

// ---------------------------------------------------------------
// 0) Include dependencies
// Adjusted paths: this file is in /checklist so we go up one level
// ---------------------------------------------------------------
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../auth/functions.php';

// ---------------------------------------------------------------
// 0.1) Define lightweight helper functions if missing
// ---------------------------------------------------------------
if (!function_exists('require_csrf')) {
    function require_csrf(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $t   = $_POST['csrf_token'] ?? '';
            $ref = $_SESSION['csrf_token'] ?? '';
            if (!$t || !$ref || !hash_equals($ref, $t)) {
                http_response_code(400);
                exit(json_encode(['success' => false, 'message' => 'Invalid CSRF token.']));
            }
        }
    }
}
if (!function_exists('post_int')) {
    function post_int(string $key): ?int {
        if (!isset($_POST[$key])) return null;
        $v = trim((string)$_POST[$key]);
        if ($v === '' || !ctype_digit($v)) return null;
        return (int)$v;
    }
}
if (!function_exists('current_user_id')) {
    function current_user_id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }
}

// ---------------------------------------------------------------
// 1) Security checks
// ---------------------------------------------------------------
require_login();
require_csrf();
header('Content-Type: application/json');

// ---------------------------------------------------------------
// 2) Input validation
// ---------------------------------------------------------------
$checklist_id = post_int('checklist_id');
$is_completed = post_int('is_completed'); // 0 or 1

if (!$checklist_id || ($is_completed !== 0 && $is_completed !== 1)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

// ---------------------------------------------------------------
// 3) Authorization: Admin only
// ---------------------------------------------------------------
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

// ---------------------------------------------------------------
// 4) Fetch checklist item for integrity
// ---------------------------------------------------------------
$stmt = $conn->prepare('SELECT id, project_id FROM project_checklists WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Unexpected error (prepare failed).']);
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

// ---------------------------------------------------------------
// 5) Toggle completion status
// ---------------------------------------------------------------
if ($is_completed === 1) {
    $stmt = $conn->prepare('
        UPDATE project_checklists
        SET is_completed = 1,
            completed_by_user_id = ?,
            completed_at = NOW()
        WHERE id = ?
        LIMIT 1
    ');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed (update true).']);
        exit;
    }
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
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed (update false).']);
        exit;
    }
    $stmt->bind_param('i', $checklist_id);
}

// ---------------------------------------------------------------
// 6) Execute update
// ---------------------------------------------------------------
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
    $stmt->close();
    exit;
}
$stmt->close();

// ---------------------------------------------------------------
// 7) Success
// ---------------------------------------------------------------
echo json_encode(['success' => true]);
exit;
?>
