<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

date_default_timezone_set('Asia/Manila');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$report_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$project_id = 0; // Initialize project ID for redirection

if ($report_id === 0) {
    // If no report ID, redirect to projects list or a general error page
    $_SESSION['status_message'] = '<div class="alert error">Error: No Report ID provided for deletion.</div>';
    header('Location: projects.php');
    exit();
}

// --- Fetch report details to get project_id and check authorization ---
$stmt_fetch_report = $conn->prepare("SELECT project_id, constructor_id FROM construction_reports WHERE id = ?");
$stmt_fetch_report->bind_param("i", $report_id);
$stmt_fetch_report->execute();
$result_fetch_report = $stmt_fetch_report->get_result();
$report_details = $result_fetch_report->fetch_assoc();
$stmt_fetch_report->close();

if (!$report_details) {
    $_SESSION['status_message'] = '<div class="alert error">Error: Report not found.</div>';
    header('Location: projects.php'); // Redirect if report doesn't exist
    exit();
}

$project_id = $report_details['project_id'];
$report_constructor_id = $report_details['constructor_id'];

// Authorization Check: Only admin or the constructor who submitted the report can delete
if (!is_admin() && $_SESSION['user_id'] != $report_constructor_id) {
    $_SESSION['status_message'] = '<div class="alert error">Unauthorized to delete this report.</div>';
    header("Location: view_project.php?id=" . $project_id);
    exit();
}

// --- Proceed with deletion using a transaction for data integrity ---
$conn->begin_transaction();
try {
    // 1. Delete associated material usage records first (due to FOREIGN KEY constraints)
    $stmt_delete_usage = $conn->prepare("DELETE FROM report_material_usage WHERE report_id = ?");
    $stmt_delete_usage->bind_param("i", $report_id);
    if (!$stmt_delete_usage->execute()) {
        throw new Exception("Error deleting associated material usages: " . $stmt_delete_usage->error);
    }
    $stmt_delete_usage->close();

    // 2. Delete the main construction report record
    $stmt_delete_report = $conn->prepare("DELETE FROM construction_reports WHERE id = ?");
    $stmt_delete_report->bind_param("i", $report_id);
    if (!$stmt_delete_report->execute()) {
        throw new Exception("Error deleting daily report: " . $stmt_delete_report->error);
    }
    $stmt_delete_report->close();

    $conn->commit();
    $_SESSION['status_message'] = '<div class="alert success">Daily report and associated materials deleted successfully!</div>';
    header("Location: view_project.php?id=" . $project_id);
    exit();

} catch (Exception $e) {
    $conn->rollback(); // Rollback on any error
    $_SESSION['status_message'] = '<div class="alert error">Error deleting report: ' . htmlspecialchars($e->getMessage()) . '</div>';
    header("Location: view_project.php?id=" . $project_id);
    exit();
}
?>