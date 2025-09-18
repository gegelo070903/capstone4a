<?php
// Start session and check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure the user_id is set. If not, redirect to login.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include functions to use is_admin()
include_once 'functions.php'; // Ensure functions.php is included if not already globally
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Construction Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
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
        z-index: 1001; /* Ensure sidebar is above other content, but header above it if needed */
    }
    .sidebar-logo { text-align: center; margin-bottom: 40px; }
    
    .sidebar-logo img { max-width: 120px; }

    .sidebar-nav a { display: block; color: #333; text-decoration: none; padding: 15px 20px; border-radius: 8px; margin-bottom: 10px; font-weight: 500; }
    .sidebar-nav a:hover, .sidebar-nav a.active { background-color: #eef2f7; color: #007bff; }

    /* Main Container for all content to the right of the sidebar */
    .main-container {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        margin-left: 250px; /* This must match the sidebar width */
        
        /* NEW: Add padding-top to push content below the sticky header */
        padding-top: 70px; /* Adjust this value if your header's height changes */
    }

    /* Top Header Bar Styling */
    .main-header {
        position: fixed;   /* Sticks it to the viewport */
        top: 0;            /* Aligns it to the top edge */
        left: 250px;       /* Starts after the fixed sidebar */
        width: calc(100% - 250px); /* Takes full width minus sidebar width */
        
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: #ffffff;
        padding: 15px 40px;
        border-bottom: 1px solid #e1e8ed;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); /* Optional: add a subtle shadow */
        z-index: 1000;     /* Ensures it stays above other content */
        box-sizing: border-box; /* Ensures padding is included in the width calculation */
    }
    .header-left h1 { margin: 0; font-size: 24px; font-weight: 700; color: #2c3e50; }
    
    /* MODIFIED: Adjusted header-right for just the avatar */
    .header-right { 
        display: flex; 
        align-items: center; 
        gap: 0; /* No gap needed if only one item */
    }
    
    /* Removed .search-bar styles as the element is removed */
    .admin-profile{position:relative;cursor:pointer}.admin-avatar{width:40px;height:40px;border-radius:50%;object-fit:cover}
    
    /* The area where page-specific content will go */
    .main-content {
        padding: 40px; /* Provides internal spacing for the content boxes */
        flex-grow: 1;
        /* No need for margin-top here because main-container has padding-top */
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
            <a href="development_monitoring.php">Development Monitoring</a>
            
            <?php if (is_admin()): ?>
            <a href="full_reports.php">Full Reports</a>
            <?php endif; ?>
            
            <a href="logout.php">Logout</a>
        </nav>
    </div>

    <!-- ===== MAIN CONTAINER (holds top bar and page content) ===== -->
    <div class="main-container">

        <!-- ===== TOP HEADER BAR ===== -->
        <header class="main-header">
            <div class="header-left">
                <h1>Welcome, <?= htmlspecialchars($_SESSION['username']); ?></h1>
            </div>
            <div class="header-right">
                <div class="admin-profile">
                    <img src="images/cat.jpg" alt="Admin Avatar" class="admin-avatar">
                </div>
            </div>
        </header>

        <!-- This is where the content from dashboard.php, projects.php, etc., will be inserted -->
        <div class="main-content">
