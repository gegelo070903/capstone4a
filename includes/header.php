<?php
// Start session and check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Construction Management System</title>
    
    <style>
        /* Basic Reset */
        body, html { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f6fa; }

        /* Main Page Layout Wrapper */
        .page-wrapper { display: flex; }

        /* Sidebar Styling */
        .sidebar {
            width: 250px;
            background-color: #ffffff;
            height: 100vh; /* Full height */
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            position: fixed; /* Keep it in place */
            left: 0;
            top: 0;
            box-sizing: border-box; /* Ensures padding is included in the width */
        }
        .sidebar-logo { text-align: center; margin-bottom: 40px; }
        
        /* ======================================================= */
        /* CHANGE 1: Logo size reduced for better visual balance */
        /* ======================================================= */
        .sidebar-logo img { max-width: 120px; }

        .sidebar-nav a { display: block; color: #333; text-decoration: none; padding: 15px 20px; border-radius: 8px; margin-bottom: 10px; font-weight: 500; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background-color: #eef2f7; color: #007bff; }

        /* Main Container for all content to the right of the sidebar */
        /* =============================================================== */
        /* CHANGE 2: Added margin-left to push content away from sidebar */
        /* =============================================================== */
        .main-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            margin-left: 250px; /* This must match the sidebar width */
        }

        /* Top Header Bar Styling */
        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #ffffff;
            padding: 15px 40px;
            border-bottom: 1px solid #e1e8ed;
        }
        .header-left h1 { margin: 0; font-size: 24px; font-weight: 700; color: #2c3e50; }
        .header-right { display: flex; align-items: center; gap: 25px; }
        .search-bar{display:flex;align-items:center;border:1px solid #ccc;border-radius:24px;padding:5px 8px;width:350px}.search-bar input{border:none;outline:none;width:100%;padding:5px;font-size:14px;background-color:transparent}.search-bar button{background-color:#007bff;border:none;color:white;width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;justify-content:center;align-items:center;font-size:14px}
        .admin-profile{position:relative;cursor:pointer}.admin-avatar{width:40px;height:40px;border-radius:50%;object-fit:cover}
        
        /* The area where page-specific content will go */
        .main-content {
            padding: 40px; /* Provides internal spacing for the content boxes */
            flex-grow: 1;
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <a href="dashboard.php">
                <img src="images/Sunshine Sapphire Construction and Supply Logo.png" alt="Logo - Go to Dashboard">
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="projects.php">Projects</a>
            <a href="supply_monitoring.php">Supply Monitoring</a>
            <a href="Development_monitoring.php">Development Monitoring</a>
            <a href="full_reports.php">Full Reports</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>

    <!-- ===== MAIN CONTAINER (holds top bar and page content) ===== -->
    <div class="main-container">

        <!-- ===== TOP HEADER BAR (This was missing before) ===== -->
        <header class="main-header">
            <div class="header-left">
                <h1>Welcome, <?= htmlspecialchars($_SESSION['username']); ?></h1>
            </div>
            <div class="header-right">
                <form action="projects.php" method="GET" class="search-bar">
                    <input type="text" name="query" placeholder="Search project or constructor">
                    <button type="submit">🔍</button>
                </form>
                <div class="admin-profile">
                    <img src="images/cat.jpg" alt="Admin Avatar" class="admin-avatar">
                </div>
            </div>
        </header>

        <!-- This is where the content from dashboard.php, projects.php, etc., will be inserted -->
        <div class="main-content">