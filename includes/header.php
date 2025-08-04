<?php include 'auth.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="css/style.css">
    <title>Construction Monitoring</title>
</head>
<body>
<nav class="sidebar">
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="supply_monitoring.php">Supply Monitoring</a></li>
        <li><a href="development_monitoring.php">Development Monitoring</a></li>
        <?php if (is_admin()): ?>
        <li><a href="full_reports.php">Full Reports</a></li>
        <?php endif; ?>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>
<div class="main-content">