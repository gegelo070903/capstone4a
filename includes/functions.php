<?php
// ===============================================================
// includes/functions.php
// Unified Functions for Authentication, Flash Messages, and Helpers
// ===============================================================

// ---------------------------------------------------------------
// SESSION MANAGEMENT
// ---------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple helper to ensure session is always started safely
function ensure_session_started(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// ---------------------------------------------------------------
// HTML ESCAPING
// ---------------------------------------------------------------
function h($value): string {
    if (is_null($value)) return '';
    if (is_array($value)) return htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------
// AUTHENTICATION HELPERS
// ---------------------------------------------------------------

// ✅ Require login before proceeding
function require_login(): void {
    ensure_session_started();
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// ✅ Check if logged-in user is admin
function is_admin(): bool {
    ensure_session_started();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// ✅ Require admin access
function require_admin(): void {
    if (!is_admin()) {
        header('Location: dashboard.php?error=unauthorized');
        exit();
    }
}

// ✅ Get current user ID safely
function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

// ---------------------------------------------------------------
// PROJECT ACCESS AUTHORIZATION
// Restricts constructors to their assigned projects only.
// ---------------------------------------------------------------
function authorize_project_access(mysqli $conn, int $project_id): void {
    ensure_session_started();

    $user_id   = $_SESSION['user_id']   ?? null;
    $user_role = $_SESSION['user_role'] ?? '';

    // Admins always have access
    if ($user_role === 'admin') {
        return;
    }

    if (!$user_id || !$project_id) {
        http_response_code(400);
        exit('Invalid request.');
    }

    // Check if project belongs to the constructor
    $stmt = $conn->prepare('SELECT id FROM projects WHERE id = ? AND assigned_to = ? LIMIT 1');
    if (!$stmt) {
        error_log('authorize_project_access() prepare failed: ' . $conn->error);
        http_response_code(500);
        exit('Server error.');
    }

    $stmt->bind_param('ii', $project_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    $has_access = $stmt->num_rows > 0;
    $stmt->close();

    if (!$has_access) {
        http_response_code(403);
        exit('Forbidden: You are not authorized to access this project.');
    }
}

// ---------------------------------------------------------------
// FLASH MESSAGE SYSTEM
// ---------------------------------------------------------------
function flash_set(string $type, string $message): void {
    ensure_session_started();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_display(): void {
    ensure_session_started();
    if (!empty($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $msg  = h($_SESSION['flash']['message']);

        $color = match ($type) {
            'ok', 'success'   => '#d4edda',
            'error', 'danger' => '#f8d7da',
            'warning'         => '#fff3cd',
            default           => '#e2e3e5',
        };

        echo '<div style="margin:15px 0;padding:10px;border-radius:6px;background:' . $color . ';font-weight:600;">' . $msg . '</div>';
        unset($_SESSION['flash']);
    }
}

// Legacy alias (kept for compatibility)
function flash_message(): void {
    flash_display();
}

// ---------------------------------------------------------------
// REDIRECT HELPERS
// ---------------------------------------------------------------
function safe_redirect(string $url): void {
    header("Location: $url");
    exit();
}

function redirect_with_message(string $url, string $message): void {
    ensure_session_started();
    $_SESSION['flash'] = ['type' => 'info', 'message' => $message];
    header("Location: $url");
    exit();
}

// ---------------------------------------------------------------
// CSRF TOKEN HELPERS (Optional)
// ---------------------------------------------------------------
function generate_csrf_token(): string {
    ensure_session_started();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void {
    ensure_session_started();
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}