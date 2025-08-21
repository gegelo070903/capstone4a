<?php include 'auth.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="css/style.css">
    <title>Sunshine Sapphire Construction and Supply, Inc.</title>

    <!-- NEW CSS FOR THE PROFILE DROPDOWN MENU -->
    <style>
        /* This makes the profile area clickable and prepares it for the dropdown */
        .admin-profile {
            position: relative; /* Crucial for positioning the dropdown */
            cursor: pointer; /* Changes the cursor to a pointer to show it's clickable */
        }
        
        /* The hidden dropdown menu container */
        .profile-dropdown {
            display: none; /* Hidden by default */
            position: absolute;
            top: 55px; /* Position it below the profile button */
            right: 0;
            background-color: #ffffff;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 8px;
        }

        /* The links inside the dropdown */
        .profile-dropdown a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }

        /* The hover effect for the links */
        .profile-dropdown a:hover {
            background-color: #f1f1f1;
        }

        /* This class will be added by JavaScript to show the menu */
        .show {
            display: block;
        }
    </style>
</head>
<body>
<nav class="sidebar">
    <div class="sidebar-logo">
        <a href="dashboard.php">
        <img src="images/Sunshine Sapphire Construction and Supply Logo.png" alt="Logo" style="width: 100px;">
        </a>
    </div>
    <ul>
        <li><a href="supply_monitoring.php">Supply Monitoring</a></li>
        <li><a href="development_monitoring.php">Development Monitoring</a></li>
        <?php if (is_admin()): ?>
        <li><a href="full_reports.php">Full Reports</a></li>
        <?php endif; ?>
    </ul>
</nav>
<div class="main-content">