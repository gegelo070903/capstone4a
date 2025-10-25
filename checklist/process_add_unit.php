<?php
// ===========================================
// process_add_unit.php
// Handles adding a new house/unit under a project
// ===========================================

// ---------------------------------------------------------------
// Include database and helper files
// Adjusted paths: this file is in /checklist so we go up one level
// ---------------------------------------------------------------
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../auth/functions.php';

// ---------------------------------------------------------------
// Define lightweight helper functions if missing
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
    function post_string(string $key, string $default = ''): string {
        return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
    }
}

// ---------------------------------------------------------------
// Require valid login and CSRF token
// ---------------------------------------------------------------
require_login();
require_csrf();

// ---------------------------------------------------------------
// Get POST data securely
// ---------------------------------------------------------------
$project_id  = post_int('project_id');
$name        = trim(post_string('name'));
$description = trim(post_string('description', ''));

// ---------------------------------------------------------------
// Validate input
// ---------------------------------------------------------------
if (!$project_id || $name === '') {
    die('Invalid data submitted. Please go back and try again.');
}

// ---------------------------------------------------------------
// Insert new house/unit
// ---------------------------------------------------------------
$stmt = $conn->prepare("INSERT INTO project_units (project_id, name, description) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $project_id, $name, $description);

if ($stmt->execute()) {
    // ✅ Success: redirect back to project page
    header("Location: ../modules/view_project.php?id=" . $project_id . "&status=unit_added_success");
    exit();
} else {
    // Database error
    error_log("Error adding unit: " . $stmt->error);
    header("Location: ../modules/view_project.php?id=" . $project_id . "&status=unit_added_error");
    exit();
}
?>
