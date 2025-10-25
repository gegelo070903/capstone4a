<?php
// ======================================================
// logout.php
// Clears the session and redirects to login
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy all session data
session_unset();
session_destroy();

// Redirect back to login
header("Location: login.php");
exit();
?>
