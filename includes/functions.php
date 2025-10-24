<?php
// =======================================
// includes/functions.php
// =======================================

// ✅ Safe HTML escaping helper
function h($v): string {
    // Convert NULL, arrays, or unexpected types to a safe string
    if (is_null($v)) {
        return '';
    } elseif (is_array($v)) {
        return htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8');
    } else {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// ✅ Session check: ensure the user is logged in
function require_login(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// ✅ Check if user is admin
function is_admin(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// ✅ Optional: Redirect non-admins safely
function require_admin(): void {
    if (!is_admin()) {
        header('Location: projects.php?error=unauthorized');
        exit();
    }
}

// ✅ Flash message helper (optional but useful)
function flash_message(): void {
    if (!empty($_SESSION['flash_message'])) {
        echo '<div style="padding:10px; margin:10px 0; background:#e0f2fe; border:1px solid #60a5fa; border-radius:6px; color:#1e3a8a;">'
            . h($_SESSION['flash_message']) .
            '</div>';
        unset($_SESSION['flash_message']);
    }
}

// ✅ Simple redirect helper
function redirect_with_message(string $url, string $message): void {
    $_SESSION['flash_message'] = $message;
    header("Location: $url");
    exit();
}
?>
