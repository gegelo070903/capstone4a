<?php
// ======================================================
// logout.php
// Clears the session and redirects to login
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Log the logout activity before destroying session
if (isset($_SESSION['user_id'])) {
    log_activity($conn, 'LOGOUT', 'User logged out');
}

// Destroy all session data
session_unset();
session_destroy();

// Redirect back to login
header("Location: login.php");
exit();
?>
