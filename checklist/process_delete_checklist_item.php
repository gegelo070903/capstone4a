<?php
// ===============================================================
// process_delete_checklist_item.php
// Deletes a checklist item (admin-only).
// Called via fetch() in view_project.php; returns 204 or JSON response.
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

// ---------------------------------------------------------------
// 1) Security: Login + CSRF protection
// ---------------------------------------------------------------
require_login();
require_csrf();

// ---------------------------------------------------------------
// 2) Input validation
// ---------------------------------------------------------------
header('Content-Type: application/json');
$id = post_int('id');
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid id.']);
    exit;
}

// ---------------------------------------------------------------
// 3) Authorization: Only admins can delete items
// ---------------------------------------------------------------
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

// ---------------------------------------------------------------
// 4) Integrity: Ensure item exists
// ---------------------------------------------------------------
$stmt = $conn->prepare('SELECT id FROM project_checklists WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->store_result();
$exists = $stmt->num_rows === 1;
$stmt->close();

if (!$exists) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found.']);
    exit;
}

// ---------------------------------------------------------------
// 5) Delete the checklist item
// ---------------------------------------------------------------
$stmt = $conn->prepare('DELETE FROM project_checklists WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Delete failed.']);
    exit;
}

// ---------------------------------------------------------------
// 6) Success — 204 No Content (fetch() caller handles UI reload)
// ---------------------------------------------------------------
http_response_code(204);
exit;
?>
