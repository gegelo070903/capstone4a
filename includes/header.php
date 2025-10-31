<?php
// ===============================================================
// includes/header.php
// Sunshine Sapphire Dashboard Header + Sidebar (Modern layout)
// ===============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Enforce login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Determine correct base path for assets and links
$basePath = (basename(dirname($_SERVER['PHP_SELF'])) === 'capstone') ? '' : '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sunshine Sapphire</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        /* ========= General Layout ========= */
        body {
            margin: 0;
            font-family: "Segoe UI", sans-serif;
            background-color: #f4f6f9;
            color: #2c3e50;
        }

        /* ========= Sidebar ========= */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background-color: #ffffff;
            color: #2c3e50;
            padding: 20px 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid #e5e7eb;
        }

        /* ========= Logo & User ========= */
        .sidebar-top {
            text-align: center;
        }

        .logo-container {
            display: block;
            text-align: center;
            margin-bottom: 10px;
        }

        .logo-container img {
            width: 130px; /* Enlarged logo */
            height: auto;
        }

        .user-info {
            color: #374151;
            font-weight: 600;
            font-size: 15px;
            margin-top: 8px;
        }

        .sidebar-divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 15px 0;
        }

        /* ========= Sidebar Links ========= */
        .sidebar-links {
            flex-grow: 1;
        }

        .sidebar-links a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: #4b5563;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            font-size: 15px;
            transition: background 0.3s, color 0.3s;
        }

        .sidebar-links a:hover {
            background-color: #f3f4f6;
            color: #009688;
        }

        .sidebar-links a.active {
            background-color: #009688;
            color: #ffffff;
            font-weight: 600;
        }

        .sidebar-links i {
            width: 22px;
            text-align: center;
            font-size: 16px;
        }

        /* ========= Logout Section ========= */
        .sidebar-bottom {
            text-align: left;
            margin-top: auto;
            padding-left: 20px; /* slight padding for balance */
        }

        .logout-btn {
            display: inline-block;
            background-color: #e74c3c;
            color: #fff;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            border: none;
            text-decoration: none;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background-color: #c0392b;
        }

        /* ========= Main Content ========= */
        .main-content {
            margin-left: 270px; /* space between sidebar and content */
            padding: 30px 40px;
            background-color: #f8fafc;
            min-height: 100vh;
        }

        .top-bar {
            display: none; /* Hidden now; user info moved to sidebar */
        }

        footer {
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            padding-top: 15px;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-top">
        <!-- Logo links to Dashboard -->
        <a href="<?= $basePath ?>dashboard.php" class="logo-container">
            <img src="<?= $basePath ?>assets/images/Sunshine Sapphire Construction and Supply Logo.png" alt="Sunshine Sapphire Logo">
        </a>
        <div class="user-info"><?= htmlspecialchars($_SESSION['user_role'] ?? 'User') ?></div>
        <div class="sidebar-divider"></div>

        <div class="sidebar-links">
            <a href="<?= $basePath ?>dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>
            <a href="<?= $basePath ?>uploads/projects.php" class="<?= basename($_SERVER['PHP_SELF']) === 'projects.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-folder-open"></i> Projects
            </a>
            <a href="<?= $basePath ?>development/development_monitoring.php" class="<?= basename($_SERVER['PHP_SELF']) === 'development_monitoring.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-helmet-safety"></i> Development
            </a>
            <a href="<?= $basePath ?>reports/reports.php" class="<?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-file-lines"></i> Reports
            </a>
            <a href="<?= $basePath ?>materials.php" class="<?= basename($_SERVER['PHP_SELF']) === 'materials.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-boxes-stacked"></i> Materials
            </a>
        </div>
    </div>

    <div class="sidebar-bottom">
        <a href="<?= $basePath ?>auth/logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
        <footer>© <?= date('Y') ?> Sunshine Sapphire</footer>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
