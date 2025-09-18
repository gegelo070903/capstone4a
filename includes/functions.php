<?php
// Start the session if it hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the currently logged-in user has the 'admin' role.
 *
 * @return bool Returns true if the user is an admin, false otherwise.
 */
function is_admin() {
    // Check if 'user_role' is set in the session and if it equals 'admin'
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        return true;
    }
    return false;
}