<?php
// ===============================================================
// process_add_checklist_item.php
// Creates a checklist item for a specific house/unit in a project
// Security: login required, CSRF, server-side validation, auth check
// ===============================================================

// ---------------------------------------------------------------
// 0) Include dependencies (DB + helper functions)
// Adjusted paths: this file is in /checklist so we go up one level
// ---------------------------------------------------------------
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';  // sanitize(), etc.
require_once __DIR__ . '/../auth/functions.php';      // require_login(), is_admin(), flash helpers

// ---------------------------------------------------------------
// 0.1) Local helpers to match your usage (if not globally available)
// ---------------------------------------------------------------
if (!function_exists('require_csrf')) {
    function require_csrf(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $t   = $_POST['csrf_token'] ?? '';
            $ref = $_SESSION['csrf_token'] ?? '';
            if (!$t || !$ref || !hash_equals($ref, $t)) {
                http_response_code(400);
                exit('Invalid CSRF token.');
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
if (!function_exists('post_string')) {
    function post_string(string $key): ?string {
        if (!isset($_POST[$key])) return null;
        $v = trim((string)$_POST[$key]);
        return ($v === '') ? null : $v;
    }
}

// Ensure user is logged in and CSRF token is valid
require_login();
require_csrf();

// ---------------------------------------------------------------
// 1) Gather and validate POST input
// ---------------------------------------------------------------
$project_id        = post_int('project_id');
$unit_id           = post_int('unit_id');                 // required (per-house checklist)
$item_description  = post_string('item_description');     // required

if (!$project_id || !$unit_id || !$item_description) {
    // Minimal error path; keep the user experience simple
    header('Location: ../modules/view_project.php?id=' . (int)$project_id . '&status=checklist_item_added_error&message=Missing+required+fields');
    exit;
}

// ---------------------------------------------------------------
// 2) Authorization: Only admins (or extend to constructor if desired)
//    You display the button to admins only; enforce that here too.
// ---------------------------------------------------------------
if (!is_admin()) {
    http_response_code(403);
    exit('Forbidden: Only admins can add checklist items.');
}

// Optional: If you’d like to allow assigned constructor too, replace the block above with:
// authorize_project_access($conn, $project_id);

// ---------------------------------------------------------------
// 3) Integrity check: ensure the unit belongs to the project
//    Prevent tampering (posting a unit_id from another project)
// ---------------------------------------------------------------
$ok_unit = false;
$stmt = $conn->prepare('SELECT id FROM project_units WHERE id = ? AND project_id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('ii', $unit_id, $project_id);
    $stmt->execute();
    $stmt->store_result();
    $ok_unit = $stmt->num_rows === 1;
    $stmt->close();
}
if (!$ok_unit) {
    header('Location: ../modules/view_project.php?id=' . (int)$project_id . '&status=checklist_item_added_error&message=Invalid+unit');
    exit;
}

// ---------------------------------------------------------------
// 4) Insert checklist item (default is_completed = 0)
// ---------------------------------------------------------------
$stmt = $conn->prepare('
    INSERT INTO project_checklists
        (project_id, unit_id, item_description, is_completed, created_at)
    VALUES
        (?, ?, ?, 0, NOW())
');
if (!$stmt) {
    error_log('prepare failed: ' . $conn->error);
    header('Location: ../modules/view_project.php?id=' . (int)$project_id . '&status=checklist_item_added_error');
    exit;
}

$stmt->bind_param('iis', $project_id, $unit_id, $item_description);

if (!$stmt->execute()) {
    error_log('insert failed: ' . $stmt->error);
    $stmt->close();
    header('Location: ../modules/view_project.php?id=' . (int)$project_id . '&status=checklist_item_added_error');
    exit;
}

$stmt->close();

// ---------------------------------------------------------------
// 5) Success -> redirect back to project view
// ---------------------------------------------------------------
header('Location: ../modules/view_project.php?id=' . (int)$project_id . '&status=checklist_item_added_success');
exit;
