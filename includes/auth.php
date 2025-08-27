<?php

// ======================================================================
// FIX 1: Only start a session if one is not already active.
// This prevents the "session is already active" error.
// ======================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login page if user is not logged in at all.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


// ======================================================================
// FIX 2: Update functions to use the correct session variable: 'user_role'.
// This ensures consistency with your login script and all other pages.
// ======================================================================

/**
 * Checks if the currently logged-in user is an admin.
 * @return bool
 */
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Checks if the currently logged-in user is a constructor.
 * @return bool
 */
function is_constructor() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'constructor';
}

?>