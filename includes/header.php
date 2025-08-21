<?php include 'auth.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="css/style.css">
    <title>Sunshine Sapphire Construction and Supply, Inc.</title>
</head>
<body>
<nav class="sidebar">
    <div>
        <a href = "dashboard.php">
        <img src="images/Sunshine Sapphire  Construction and Supply Logo.png" alt="Logo" style="width: 100 px;">
        </a>
    </div>
    <ul>
        <li><a href="supply_monitoring.php">Supply Monitoring</a></li>
        <li><a href="development_monitoring.php">Development Monitoring</a></li>
        <?php if (is_admin()): ?>
        <li><a href="full_reports.php">Full Reports</a></li>
        <?php endif; ?>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>
<div class="main-content">