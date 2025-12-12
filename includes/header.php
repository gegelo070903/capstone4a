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

        /* ========= Toast Notifications ========= */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 380px;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease-out;
            font-size: 14px;
            font-weight: 500;
            min-width: 280px;
        }

        .toast.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .toast.error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .toast.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .toast.info {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .toast i {
            font-size: 18px;
        }

        .toast .toast-message {
            flex: 1;
        }

        .toast .toast-close {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 16px;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .toast .toast-close:hover {
            opacity: 1;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .toast.hiding {
            animation: slideOut 0.3s ease-in forwards;
        }
    </style>
</head>
<body>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// Global Toast Notification System
function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast ' + type;

    let icon = 'fa-circle-info';
    if (type === 'success') icon = 'fa-circle-check';
    else if (type === 'error') icon = 'fa-circle-xmark';
    else if (type === 'warning') icon = 'fa-triangle-exclamation';

    toast.innerHTML = `
        <i class="fa-solid ${icon}"></i>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="closeToast(this)"><i class="fa-solid fa-xmark"></i></button>
    `;

    container.appendChild(toast);

    // Auto remove after duration
    setTimeout(() => {
        if (toast.parentNode) {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        }
    }, duration);
}

function closeToast(btn) {
    const toast = btn.closest('.toast');
    if (toast) {
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 300);
    }
}

// Check for URL parameters for toast messages
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const message = urlParams.get('message');

    if (status === 'success' && message) {
        showToast(decodeURIComponent(message), 'success');
        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (status === 'error' && message) {
        showToast(decodeURIComponent(message), 'error');
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (status === 'warning' && message) {
        showToast(decodeURIComponent(message), 'warning');
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-top">
        <!-- Logo links to Dashboard -->
        <a href="<?= $basePath ?>dashboard.php" class="logo-container">
            <img src="<?= $basePath ?>assets/images/Sunshine_Sapphire_Construction_and_Supply_Logo.png" alt="Sunshine Sapphire Logo">
        </a>
        <div class="user-info"><?= htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username'] ?? 'User') ?></div>
        <div class="sidebar-divider"></div>

        <div class="sidebar-links">
            <a href="<?= $basePath ?>dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>
            <a href="<?= $basePath ?>uploads/projects.php" class="<?= basename($_SERVER['PHP_SELF']) === 'projects.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-folder-open"></i> Projects
            </a>
            <a href="<?= $basePath ?>materials/materials.php" class="<?= basename($_SERVER['PHP_SELF']) === 'materials.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-boxes-stacked"></i> Materials
            </a>
            <a href="<?= $basePath ?>reports/reports.php" class="<?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-file-lines"></i> Reports
            </a>
            <?php if (is_admin()): ?>
            <a href="<?= $basePath ?>auth/accounts.php" class="<?= basename($_SERVER['PHP_SELF']) === 'accounts.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-gear"></i> Accounts
            </a>
            <?php endif; ?>
            <?php if (is_super_admin()): ?>
            <a href="<?= $basePath ?>auth/activity_logs.php" class="<?= basename($_SERVER['PHP_SELF']) === 'activity_logs.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-clock-rotate-left"></i> Activity Logs
            </a>
            <?php endif; ?>
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