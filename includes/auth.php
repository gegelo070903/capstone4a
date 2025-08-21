<?php
// log in function nga may administrative role
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
function is_constructor() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'constructor';
}
?>