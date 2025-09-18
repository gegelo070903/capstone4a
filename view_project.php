<?php
// view_project.php

include 'includes/db.php';
include 'includes/functions.php';
include 'includes/header.php'; // Assumes this sets up the main page wrapper and sticky header

// Set timezone for date/time functions to work correctly
date_default_timezone_set('Asia/Manila');

// --- 1. Get the project ID from the URL ---
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<p class='error-message'>Invalid Project ID provided.</p>";
    exit();
}
$project_id = intval($_GET['id']);

// --- 2. Fetch the project details ---
$stmt_project = $conn->prepare("SELECT p.*, p.location, u.username AS constructor_name
                                FROM projects p
                                LEFT JOIN users u ON p.constructor_id = u.id
                                WHERE p.id = ?");
$stmt_project->bind_param("i", $project_id);
$stmt_project->execute();
$project_result = $stmt_project->get_result();
$project = $project_result->fetch_assoc();

if (!$project) {
    echo "<p class='error-message'>Project not found.</p>";
    exit();
}

// --- Determine if the logged-in user is the assigned constructor for this project ---
$is_assigned_constructor = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $project['constructor_id']) {
    $is_assigned_constructor = true;
}

// --- 3. Fetch all materials available for selection in the daily report dropdown ---
$all_materials = [];
$material_query_sql = "";
$material_query_params = [];
$material_query_types = "";

if (is_admin()) {
    // Admin should see ALL materials in the system, regardless of project assignment
    $material_query_sql = "SELECT id, name, unit_of_measurement FROM materials ORDER BY name ASC";
    // No params needed for admin for this specific query
} else {
    // If it's a constructor, only show global materials (project_id IS NULL)
    // OR materials that are assigned to projects where the current user is the constructor.
    // Also include materials specifically assigned to THIS project
    $material_query_sql = "SELECT m.id, m.name, m.unit_of_measurement
                           FROM materials m
                           LEFT JOIN projects p ON m.project_id = p.id
                           WHERE m.project_id IS NULL OR p.constructor_id = ? OR m.project_id = ?
                           ORDER BY m.name ASC";
    $material_query_params[] = $_SESSION['user_id'];
    $material_query_params[] = $project_id; // Include materials specific to this project
    $material_query_types = "ii";
}

$stmt_all_materials = $conn->prepare($material_query_sql);
if (!empty($material_query_params)) {
    $stmt_all_materials->bind_param($material_query_types, ...$material_query_params);
}
$stmt_all_materials->execute();
$all_materials_result = $stmt_all_materials->get_result();
while ($mat = $all_materials_result->fetch_assoc()) {
    $all_materials[] = $mat;
}
$stmt_all_materials->close();


// --- 4. Fetch all materials linked to this project ID (for the project's own Materials Used table) ---
$stmt_materials = $conn->prepare("SELECT * FROM materials WHERE project_id = ? ORDER BY id DESC");
$stmt_materials->bind_param("i", $project_id);
$stmt_materials->execute();
$materials_result = $stmt_materials->get_result();

// --- 5. Fetch all development reports linked to this project ID ---
// Also fetch associated material usage for each report
$reports = [];
$stmt_reports = $conn->prepare("SELECT cr.*, u.username as reporter_name
                                FROM construction_reports cr
                                LEFT JOIN users u ON cr.constructor_id = u.id
                                WHERE cr.project_id = ? ORDER BY cr.report_date DESC, cr.id DESC");
$stmt_reports->bind_param("i", $project_id);
$stmt_reports->execute();
$reports_result = $stmt_reports->get_result();

while ($report_row = $reports_result->fetch_assoc()) {
    $report_id_current = $report_row['id'];
    $material_usages = [];

    $stmt_usage = $conn->prepare("SELECT rmu.id AS rmu_id, rmu.quantity_used,
                                         m.name AS material_name, m.unit_of_measurement
                                  FROM report_material_usage rmu
                                  JOIN materials m ON rmu.material_id = m.id
                                  WHERE rmu.report_id = ?");
    $stmt_usage->bind_param("i", $report_id_current);
    $stmt_usage->execute();
    $usage_result = $stmt_usage->get_result();
    while ($usage_row = $usage_result->fetch_assoc()) {
        $material_usages[] = $usage_row;
    }
    $stmt_usage->close();
    $report_row['material_usages'] = $material_usages;
    $reports[] = $report_row;
}

// --- 6. Fetch all checklist items for this project ID and calculate progress ---
$stmt_checklist = $conn->prepare("SELECT pc.*, u.username AS completed_by_username
                                 FROM project_checklists pc
                                 LEFT JOIN users u ON pc.completed_by_user_id = u.id
                                 WHERE pc.project_id = ? ORDER BY pc.created_at ASC");
$stmt_checklist->bind_param("i", $project_id);
$stmt_checklist->execute();
$checklist_result = $stmt_checklist->get_result();

$total_checklist_items = 0;
$completed_checklist_items = 0;
$checklist_items = []; // To store items for display

if ($checklist_result->num_rows > 0) {
    while ($item = $checklist_result->fetch_assoc()) {
        $total_checklist_items++;
        if ($item['is_completed']) {
            $completed_checklist_items++;
        }
        $checklist_items[] = $item;
    }
}

$project_completion_percentage = ($total_checklist_items > 0)
                                 ? round(($completed_checklist_items / $total_checklist_items) * 100)
                                 : 0;


// --- Handle Status Messages from various pages ---
$status_message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'report_added_success') {
        $status_message = '<div class="alert success">Daily report added successfully!</div>';
    } elseif ($_GET['status'] === 'report_added_error') {
        $error_details = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error adding daily report. Please try again.';
        $status_message = '<div class="alert error">' . $error_details . '</div>';
    } elseif ($_GET['status'] === 'project_updated_success') {
        $status_message = '<div class="alert success">Project updated successfully!</div>';
    } elseif ($_GET['status'] === 'checklist_item_added_success') {
        $status_message = '<div class="alert success">Checklist item added successfully!</div>';
    } elseif ($_GET['status'] === 'checklist_item_added_error') {
        $error_details = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error adding checklist item. Please try again.';
        $status_message = '<div class="alert error">' . $error_details . '</div>';
    } elseif ($_GET['status'] === 'checklist_item_updated_success') {
        $status_message = '<div class="alert success">Checklist item updated successfully!</div>';
    } elseif ($_GET['status'] === 'checklist_item_updated_error') {
        $error_details = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error updating checklist item. Please try again.';
        $status_message = '<div class="alert error">' . $error_details . '</div>';
    } elseif ($_GET['status'] === 'checklist_item_deleted_success') {
        $status_message = '<div class="alert success">Checklist item deleted successfully!</div>';
    } elseif ($_GET['status'] === 'checklist_item_deleted_error') {
        $error_details = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error deleting checklist item. Please try again.';
        $status_message = '<div class="alert error">' . $error_details . '</div>';
    }
}
?>
<style>
/* Main Content Wrapper (assuming it's used consistently across dashboard/projects) */
.main-content-wrapper {
    padding: 30px;
    max-width: 1200px;
    margin: 0 auto;
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
}

/* Alert messages (if not defined globally) */
.alert {
    padding: 10px; /* Slightly more padding for readability */
    border-radius: 6px;
    margin-bottom: 15px; /* More margin */
    font-weight: bold;
    text-align: center;
    font-size: 0.9em; /* More readable font size */
    opacity: 1; /* Start fully visible */
    transition: opacity 0.5s ease-out; /* Smooth fade-out transition */
}
.alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6fb; }
.alert.fade-out {
    opacity: 0;
}


/* Project Details Section */
.project-details-section {
    background-color: #ffffff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
}

.project-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 10px;
}

.project-title {
    font-size: 2.2em;
    color: #333;
    margin: 0;
}

.header-actions-group {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.project-header-buttons {
    display: flex;
    gap: 10px;
}

.project-detail-line {
    font-size: 1.0em;
    color: #555;
    margin: 3px 0;
}
.project-detail-line strong {
    color: #333;
}

.project-status-badge {
    padding: 7px 15px;
    border-radius: 20px;
    font-size: 0.9em;
    font-weight: bold;
    color: white;
    text-transform: uppercase;
    white-space: nowrap;
}

.project-status-badge.pending, .report-status-badge.ongoing.pending { background-color: #ffc107; }
.project-status-badge.ongoing, .report-status-badge.ongoing { background-color: #007bff; }
.project-status-badge.completed, .report-status-badge.complete { background-color: #28a745; }
.project-status-badge.cancelled { background-color: #dc3545; }

/* Generic Button Styles (for reuse) */
.btn {
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    transition: background-color 0.3s ease, transform 0.2s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}
.btn:hover { transform: translateY(-2px); }
.btn-primary { background-color: #007bff; color: white; }
.btn-primary:hover { background-color: #0056b3; }
.btn-danger { background-color: #dc3545; color: white; }
.btn-danger:hover { background-color: #c82333; }
.btn-sm-action { padding: 7px 12px; font-size: 0.9em; border-radius: 5px; }
.btn-edit { background-color: #ffc107; color: #333; }
.btn-edit:hover { background-color: #e0a800; }
.btn-delete { background-color: #dc3545; color: white; }
.btn-delete:hover { background-color: #c82333; }

/* Section Headers for Materials & Development */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}
.section-header h3 { font-size: 1.6em; color: #333; margin: 0; }

/* Table Styling */
.table-responsive {
    overflow-x: auto;
    margin-top: 15px;
    background-color: #ffffff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}
table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
table thead th {
    background-color: #f8f9fa;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    color: #6c757d;
    border-bottom: 2px solid #e9ecef;
}
table tbody td {
    padding: 12px 15px;
    border-bottom: 1px solid #dee2e6;
    color: #495057;
}
table tbody tr:last-child td { border-bottom: none; }
table tbody tr:hover { background-color: #f2f2f2; }

.no-data-message {
    text-align: center;
    color: #777;
    font-style: italic;
    padding: 20px;
    background-color: #ffffff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.development-section .table-responsive { margin-top: 20px; }
.report-status-badge {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8em;
    font-weight: bold;
    color: white;
    text-transform: uppercase;
}
.add-report-form-overlay,
.add-checklist-form-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1002;
    padding: 10px; /* Reduced overall padding for tighter fit */
    box-sizing: border-box;
}

.add-report-form-card,
.add-checklist-form-card {
    background-color: #ffffff;
    padding: 15px 20px; /* Reduced internal padding */
    border-radius: 10px; /* Slightly less rounded */
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); /* Softer shadow */
    width: 100%;
    max-width: 550px; /* Adjusted to optimize horizontal space */
    position: relative;
    height: auto;
    max-height: 90vh; /* Ensure modal itself doesn't exceed viewport height */
    overflow-y: auto; /* Allow internal scroll only if necessary for content */
    box-sizing: border-box;
    transform: translateY(0);
    opacity: 1;
    transition: transform 0.3s ease-out, opacity 0.3s ease-out;
}


.add-report-form-card .form-header,
.add-checklist-form-card .form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px; /* Reduced margin below header */
    border-bottom: 1px solid #eee;
    padding-bottom: 6px; /* Reduced padding below border */
    padding-top: 3px;
}

.add-report-form-card h3,
.add-checklist-form-card h3 {
    margin: 0;
    font-size: 1.3em; /* Smaller title font */
    color: #333;
    line-height: 1.2;
}

.add-report-form-card .close-btn,
.add-checklist-form-card .close-btn {
    background: none;
    border: none;
    font-size: 1.3em; /* Smaller 'X' for compactness */
    color: #888;
    cursor: pointer;
    text-decoration: none;
    line-height: 1;
    padding: 0;
    transition: color 0.2s ease;
}
.add-report-form-card .close-btn:hover,
.add-checklist-form-card .close-btn:hover {
    color: #333;
}


/* --- REVISED FORM GROUP STYLES FOR COMPACTNESS --- */
.add-report-form-card .form-group,
.add-checklist-form-card .form-group {
    margin-bottom: 8px; /* Reduced margin between groups */
}

.add-report-form-card label,
.add-checklist-form-card label {
    display: block;
    margin-bottom: 3px; /* Reduced space below label */
    font-weight: 600;
    color: #555;
    font-size: 0.85em; /* Smaller label font size */
    line-height: 1.2;
}

.add-report-form-card input[type="date"],
.add-report-form-card input[type="time"],
.add-report-form-card input[type="number"],
.add-report-form-card input[type="text"],
.add-report-form-card textarea,
.add-report-form-card select,
.add-checklist-form-card input[type="text"],
.add-checklist-form-card textarea {
    width: 100%;
    padding: 6px 9px; /* Reduced input padding */
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 0.8em; /* Smaller input font size */
    box-sizing: border-box;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.add-report-form-card input[type="date"]:focus,
.add-report-form-card input[type="time"]:focus,
.add-report-form-card input[type="number"]:focus,
.add-report-form-card input[type="text"]:focus,
.add-report-form-card textarea:focus,
.add-report-form-card select:focus,
.add-checklist-form-card input[type="text"]:focus,
.add-checklist-form-card textarea:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.15); /* Softer focus shadow */
    outline: none;
}

.add-report-form-card textarea,
.add-checklist-form-card textarea {
    min-height: 50px; /* Reduced min-height for description */
    max-height: 80px; /* Reduced max-height */
    resize: vertical;
    line-height: 1.3;
}

/* Specific styling for the file input */
.add-report-form-card input[type="file"] {
    padding: 5px 8px; /* Reduced padding */
    font-size: 0.8em; /* Smaller font */
}

/* Styles for the 'Choose File' button itself within the file input */
.add-report-form-card input[type="file"]::file-selector-button {
    padding: 5px 10px; /* Reduced padding */
    font-size: 0.8em;
}


/* --- Button Group for Submit and Cancel --- */
.form-buttons {
    display: flex;
    gap: 8px; /* Reduced gap */
    margin-top: 12px; /* Reduced space above buttons */
}

.create-report-btn, .cancel-report-btn,
.create-checklist-btn, .cancel-checklist-btn {
    padding: 9px 15px; /* Reduced padding for buttons */
    border-radius: 6px;
    font-size: 0.9em; /* Smaller font size */
    transform: translateY(0);
}
.create-report-btn:hover, .create-checklist-btn:hover,
.cancel-report-btn:hover, .cancel-checklist-btn:hover {
    transform: none;
    opacity: 0.9;
}


.form-row {
    display: flex;
    gap: 10px; /* Reduced gap between date/time inputs */
    margin-bottom: 8px; /* Reduced margin */
    flex-wrap: wrap;
}

.form-row .form-group {
    flex: 1;
    min-width: 100px; /* Adjusted min-width for very small screens */
    margin-bottom: 0;
}

/* New Styles for Project Checklist and Progress Bar (these are outside the overlay) */
.checklist-section {
    margin-top: 30px;
    background-color: #ffffff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.progress-container {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.progress-bar-wrapper {
    flex-grow: 1; /* Allows the bar to take available space */
    background-color: #e0e0e0;
    border-radius: 15px;
    height: 25px;
    overflow: hidden;
    position: relative;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    min-width: 200px; /* Ensure it doesn't get too small */
}

.progress-bar {
    height: 100%;
    width: 0; /* Will be set by JS/PHP */
    background-color: #28a745; /* Green for completion */
    border-radius: 15px;
    text-align: center;
    color: white;
    line-height: 25px;
    font-weight: bold;
    transition: width 0.5s ease-in-out;
    display: flex; /* Use flexbox for inner content */
    align-items: center; /* Vertically center content */
    justify-content: center; /* Horizontally center content */
    white-space: nowrap; /* Prevent percentage text from wrapping */
    text-shadow: 0 1px 2px rgba(0,0,0,0.2); /* For better readability on the bar */
}
/* Ensure 0% bar shows its text even if width is 0 */
.progress-bar[style*="width: 0%"] {
    color: #555; /* Darker text for 0% if bar is empty */
    background-color: transparent; /* No background if 0% */
    justify-content: flex-start; /* Align text to start if empty */
    padding-left: 5px; /* Small padding for 0% text */
}


.progress-text {
    font-size: 1.2em;
    font-weight: bold;
    color: #333;
    white-space: nowrap; /* Keep percentage on one line */
}

.checklist-items-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.checklist-item {
    display: flex;
    align-items: center; /* Aligns checkbox and text vertically centered */
    background-color: #f9f9f9;
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    border: 1px solid #eee;
    transition: background-color 0.2s ease;
}

.checklist-item.completed {
    background-color: #e6ffe6; /* Light green for completed items */
    border-color: #c8e6c9;
}

.checklist-checkbox {
    margin-right: 15px;
    min-width: 20px; /* Ensure checkbox has space */
    min-height: 20px;
    accent-color: #28a745; /* Green accent color for checkbox */
    cursor: pointer;
    flex-shrink: 0; /* Prevent checkbox from shrinking */
    /* Removed margin-top, now `align-items: center` in parent handles vertical alignment */
}

.checklist-description {
    flex-grow: 1; /* Takes up remaining space */
    font-size: 1.0em;
    color: #333;
    word-break: break-word; /* Ensure long descriptions wrap */
    line-height: 1.4;
}

.checklist-item.completed .checklist-description {
    text-decoration: line-through;
    color: #777;
}

.checklist-actions {
    display: flex;
    gap: 5px; /* Space between edit/delete buttons */
    margin-left: 15px;
    flex-shrink: 0; /* Prevent action buttons from shrinking */
}

/* Specific styling for the trash icon button */
.checklist-actions .btn-sm-action.btn-delete {
    padding: 5px 10px; /* Smaller padding for a compact icon button */
    font-size: 0.9em; /* Adjust font size if needed for icon */
}

.checklist-meta {
    font-size: 0.8em;
    color: #888;
    margin-top: 0px; /* Adjusted margin, now part of the item itself */
    padding-left: 35px; /* Align with description, after checkbox */
    margin-bottom: 10px; /* Keep consistent spacing below item */
}

.checklist-meta .completed-info {
    font-weight: 500;
    color: #155724;
}

/* NEW: Styles for stacked action buttons within a table cell */
.action-buttons-wrapper {
    display: flex;
    flex-direction: column; /* Stack buttons vertically */
    gap: 5px; /* Space between the stacked buttons */
    align-items: flex-start; /* Align buttons to the left */
}
/* Ensure the small action buttons within the wrapper inherit correct sizing */
.action-buttons-wrapper .btn {
    padding: 7px 12px; /* Reapply specific padding for small buttons */
    font-size: 0.9em; /* Reapply specific font size for small buttons */
    width: auto; /* Allow buttons to size based on content */
}

/* Material Usage Display in Reports Table */
.material-usage-list {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 0.85em;
    color: #666;
}
.material-usage-list li {
    margin-bottom: 3px;
    display: flex;
    justify-content: space-between; /* Adjusted for better spacing */
    align-items: center;
    padding: 2px 0;
    border-bottom: 1px dotted #eee;
    flex-wrap: wrap; /* Allow items to wrap if space is tight */
}
.material-usage-list li:last-child {
    border-bottom: none;
}

/* Styles for dynamic material input fields in overlay */
.materials-used-section {
    margin-top: 10px; /* Further reduced margin */
    border-top: 1px dashed #eee;
    padding-top: 10px; /* Further reduced padding */
}
.materials-used-section h4 {
    margin-top: 0;
    margin-bottom: 6px; /* Further reduced margin */
    font-size: 1.05em; /* Smaller font */
    color: #555;
}
.material-input-row {
    display: flex;
    gap: 8px; /* Reduced gap */
    margin-bottom: 6px; /* Reduced margin */
    align-items: flex-end;
    flex-wrap: wrap;
}
.material-input-row .form-group {
    flex: 1;
    margin-bottom: 0;
}
.material-input-row .form-group.qty {
    flex: 0 0 65px; /* Further reduced fixed width for quantity */
}
.material-input-row .form-group.unit {
    flex: 0 0 45px; /* Further reduced fixed width for unit display */
    display: flex;
    align-items: center;
    height: 28px; /* Match input height */
    padding-top: 18px; /* Adjusted to align text better */
    font-size: 0.75em; /* Smaller font */
    color: #777;
}
.material-input-row .remove-material-btn {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 5px 8px; /* Reduced padding */
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.7em; /* Smaller font */
    line-height: 1;
    height: 28px; /* Match input height */
}
.material-input-row .remove-material-btn:hover {
    background-color: #c82333;
}

.add-more-material-btn {
    background-color: #28a745;
    color: white;
    border: none;
    padding: 7px 10px; /* Reduced padding */
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8em; /* Smaller font */
    margin-top: 6px; /* Reduced margin */
}
.add-more-material-btn:hover {
    background-color: #218838;
}

</style>

<div class="main-content-wrapper">
    <!-- Display Status Messages here -->
    <?php echo $status_message; ?>

    <div class="project-details-section">
        <div class="project-header-row">
            <h2 class="project-title"><?= htmlspecialchars($project['name']); ?></h2>
            <div class="header-actions-group">
                <span class="project-status-badge <?= strtolower($project['status']); ?>"><?= htmlspecialchars($project['status']); ?></span>
                <?php if (is_admin()): // Admin actions for the project itself ?>
                    <div class="project-header-buttons">
                        <a href="edit_project.php?id=<?= $project['id'] ?>" class="btn btn-primary btn-sm-action">Edit</a>
                        <a href="delete_project.php?id=<?= $project['id'] ?>" class="btn btn-danger btn-sm-action" onclick="return confirm('Are you sure you want to delete this project and all its associated data?')">Delete</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <p class="project-detail-line"><strong>Location:</strong> <?= htmlspecialchars($project['location']); ?></p>
        <p class="project-detail-line"><strong>Assigned Constructor:</strong> <?= htmlspecialchars($project['constructor_name']); ?></p>
        <p class="project-detail-line"><strong>Date Created:</strong> <?= date('M d, Y', strtotime($project['created_at'])); ?></p>
    </div>

    <!-- Project Checklist and Progress Section -->
    <div class="checklist-section">
        <div class="section-header">
            <h3>Project Milestones / Checklist</h3>
            <?php if (is_admin()): ?>
                <button type="button" class="btn btn-primary" onclick="toggleAddChecklistForm()">Add Checklist Item</button>
            <?php endif; ?>
        </div>

        <div class="progress-container">
            <div class="progress-bar-wrapper">
                <div class="progress-bar" style="width: <?= $project_completion_percentage ?>%;">
                    <?= $project_completion_percentage ?>%
                </div>
            </div>
            <span class="progress-text"><?= $completed_checklist_items ?> of <?= $total_checklist_items ?> completed</span>
        </div>

        <?php if (!empty($checklist_items)): ?>
            <ul class="checklist-items-list">
                <?php foreach ($checklist_items as $item): ?>
                <li class="checklist-item <?= $item['is_completed'] ? 'completed' : '' ?>">
                    <?php if (is_admin()): ?>
                    <input type="checkbox"
                           class="checklist-checkbox"
                           data-checklist-id="<?= $item['id'] ?>"
                           <?= $item['is_completed'] ? 'checked' : '' ?>
                           onchange="toggleChecklistItem(this)">
                    <?php else: // Constructors just see a disabled checkbox ?>
                    <input type="checkbox"
                           class="checklist-checkbox"
                           <?= $item['is_completed'] ? 'checked' : '' ?> disabled>
                    <?php endif; ?>

                    <span class="checklist-description"><?= htmlspecialchars($item['item_description']) ?></span>

                    <?php if (is_admin()): ?>
                    <div class="checklist-actions">
                        <!-- Edit and Delete will be implemented later, for now just placeholder -->
                        <!-- <a href="#" class="btn btn-sm-action btn-edit" title="Edit Item">✏️</a> -->
                        <a href="process_delete_checklist_item.php?id=<?= $item['id'] ?>&project_id=<?= $project_id ?>" class="btn btn-sm-action btn-danger" title="Delete Item" onclick="return confirm('Are you sure you want to delete this checklist item?')"> <i class="fas fa-trash-alt"></i> </a>
                    </div>
                    <?php endif; ?>
                </li>
                <?php if ($item['is_completed'] && $item['completed_by_username']): ?>
                    <p class="checklist-meta completed-info">
                        Completed by: <?= htmlspecialchars($item['completed_by_username']) ?> on <?= date('M d, Y h:i A', strtotime($item['completed_at'])) ?>
                    </p>
                <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="no-data-message">No checklist items have been added for this project yet.</p>
        <?php endif; ?>
    </div>

    <hr>

    <!-- Materials Section -->
    <div class="materials-section">
        <div class="section-header">
            <h3>Materials Used</h3>
            <?php if (is_admin()): ?>
                <a href="add_material.php?project_id=<?= $project_id; ?>" class="btn btn-primary">Add Material</a>
            <?php endif; ?>
        </div>

        <?php if ($materials_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Supplier</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Purpose</th>
                            <?php if (is_admin()): ?><th>Action</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($material = $materials_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($material['name']) ?></td>
                            <td><?= htmlspecialchars($material['supplier']) ?></td>
                            <td><?= htmlspecialchars(number_format($material['quantity'], 0)) ?></td>
                            <td>₱<?= htmlspecialchars(number_format($material['price'], 0)) ?></td>
                            <td>₱<?= htmlspecialchars(number_format($material['total_amount'], 0)) ?></td>
                            <td><?= $material['date'] ?></td>
                            <td><?= htmlspecialchars($material['purpose']) ?></td>
                            <?php if (is_admin()): ?>
                            <td>
                                <a href="edit_material.php?id=<?= $material['id'] ?>" class="btn btn-sm btn-edit">Edit</a>
                                <a href="delete_material.php?id=<?= $material['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this material?')">Delete</a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="no-data-message">No materials have been added to this project yet.</p>
        <?php endif; ?>
    </div>

    <hr>

    <!-- Development Monitoring Section -->
    <div class="development-section">
        <div class="section-header">
            <h3>Daily Development Reports</h3>
            <?php if (is_admin() || $is_assigned_constructor): // Admins or assigned constructor can add reports ?>
                <!-- Changed to a button that triggers JavaScript for the overlay -->
                <button type="button" class="btn btn-primary" onclick="toggleAddReportForm()">Add Daily Report</button>
            <?php endif; ?>
        </div>

        <?php if (!empty($reports)): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Description</th>
                            <th>Materials Used</th> <!-- Changed header -->
                            <th>Reporter</th>
                            <th>Proof</th>
                            <?php if (is_admin()): ?><th>Action</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reports as $report): ?>
                        <tr>
                            <td><?= date('m-d-Y', strtotime($report['report_date'])) ?></td>
                            <td><?= date('h:i A', strtotime($report['start_time'])) ?> - <?= date('h:i A', strtotime($report['end_time'])) ?></td>
                            <td><span class="report-status-badge <?= strtolower($report['status']); ?>"><?= htmlspecialchars($report['status']) ?></span></td>
                            <td><?= htmlspecialchars($report['description']) ?></td>
                            <td>
                                <?php if (!empty($report['material_usages'])): ?>
                                    <ul class="material-usage-list">
                                        <?php foreach ($report['material_usages'] as $usage): ?>
                                            <li>
                                                <span><?= htmlspecialchars(number_format($usage['quantity_used'], 0)) ?> <?= htmlspecialchars($usage['unit_of_measurement']) ?> of <?= htmlspecialchars($usage['material_name']) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($report['reporter_name']) ?>
                            </td>
                            <td>
                                <?php if (!empty($report['proof_image'])): ?>
                                    <a href="<?= htmlspecialchars($report['proof_image']) ?>" target="_blank">View Image</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <?php if (is_admin()): ?>
                            <td>
                                <div class="action-buttons-wrapper">
                                    <a href="edit_development_report.php?id=<?= $report['id'] ?>" class="btn btn-sm-action btn-edit">Edit</a>
                                    <a href="delete_development_report.php?id=<?= $report['id'] ?>" class="btn btn-sm-action btn-danger" onclick="return confirm('Delete this report?')">Delete</a>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="no-data-message">No development reports have been added for this project yet.</p>
        <?php endif; ?>
    </div>

</div> <!-- Closes .main-content-wrapper -->
<!-- Overlay: Add Daily Report Form                            -->
<div id="addReportFormOverlay" class="add-report-form-overlay">
    <div class="add-report-form-card">
        <div class="form-header">
            <h3>Add Daily Report for Project: <?= htmlspecialchars($project['name']); ?></h3>
            <button type="button" class="close-btn" onclick="toggleAddReportForm()">&times;</button> <!-- Added Close button -->
        </div>

        <form method="POST" action="add_development_report.php" enctype="multipart/form-data">
            <input type="hidden" name="project_id" value="<?= $project_id; ?>">
            <input type="hidden" name="constructor_id" value="<?= $_SESSION['user_id']; ?>"> <!-- Auto-fill constructor_id -->

            <div class="form-row">
                <div class="form-group">
                    <label for="report_date">Report Date:</label>
                    <input type="date" id="report_date" name="report_date" value="<?= date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="start_time">Start Time:</label>
                    <input type="time" id="start_time" name="start_time" value="<?= date('H:i'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="end_time">End Time:</label>
                    <input type="time" id="end_time" name="end_time" value="<?= date('H:i'); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="status">Project Status for Today:</label>
                <select id="status" name="status" required>
                    <option value="">-- Select Status --</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="complete">Complete</option>
                    <option value="pending">Pending</option>
                </select>
            </div>

            <!-- MODIFIED: Materials Used section moved up -->
            <div class="materials-used-section">
                <h4>Materials Used Today <small>(Optional)</small></h4>
                <div id="materials-input-container">
                    <!-- Dynamic material input rows will be added here by JavaScript -->
                    <div class="material-input-row">
                        <div class="form-group" style="flex: 1;">
                            <label for="material_id_0">Material Name:</label>
                            <select name="material_ids[]" id="material_id_0" onchange="updateUnitOfMeasurement(this, 'unit_0')">
                                <option value="">-- Select Material --</option>
                                <?php foreach ($all_materials as $material): ?>
                                    <option value="<?= htmlspecialchars($material['id']) ?>" data-unit="<?= htmlspecialchars($material['unit_of_measurement']) ?>">
                                        <?= htmlspecialchars($material['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group qty">
                            <label for="quantity_used_0">Qty Used:</label>
                            <input type="number" step="1" min="1" name="quantities_used[]" id="quantity_used_0">
                        </div>
                        <div class="form-group unit">
                            <span id="unit_0">Unit</span>
                        </div>
                        <!-- No remove button for the first row -->
                    </div>
                </div>
                <button type="button" class="add-more-material-btn" onclick="addMaterialRow()">Add Another Material</button>
            </div>

            <div class="form-group">
                <label for="description">Description of Work:</label>
                <textarea id="description" name="description" rows="3" required></textarea>
            </div>


            <div class="form-group">
                <label for="proof_image">Upload Proof Image (Optional):</label>
                <input type="file" id="proof_image" name="proof_image" accept="image/jpeg, image/png, image/gif">
            </div>

            <div class="form-buttons">
                <button type="submit" class="create-report-btn">Submit Daily Report</button>
                <button type="button" class="cancel-report-btn" onclick="toggleAddReportForm()">Cancel</button>
            </div>
        </form>
    </div>
</div>
<!-- NEW Overlay: Add Checklist Item Form                      -->
<div id="addChecklistFormOverlay" class="add-checklist-form-overlay">
    <div class="add-checklist-form-card">
        <div class="form-header">
            <h3>Add New Checklist Item for Project: <?= htmlspecialchars($project['name']); ?></h3>
            <button type="button" class="close-btn" onclick="toggleAddChecklistForm()">&times;</button> <!-- Added Close button -->
        </div>

        <form method="POST" action="process_add_checklist_item.php">
            <input type="hidden" name="project_id" value="<?= $project_id; ?>">

            <div class="form-group">
                <label for="item_description">Checklist Item Description:</label>
                <textarea id="item_description" name="item_description" rows="3" placeholder="e.g., 'Foundation poured and cured', 'Electrical rough-in completed', 'Roofing installed'" required></textarea>
            </div>

            <div class="form-buttons">
                <button type="submit" class="create-checklist-btn">Add Item</button>
                <button type="button" class="cancel-checklist-btn" onclick="toggleAddChecklistForm()">Cancel</button>
            </div>
        </form>
    </div>
</div>

</div> <!-- This closing div likely comes from your header.php file (for .main-container) -->

<script>
let materialRowCounter = 0; // Global counter for dynamic material input rows
const allMaterials = <?= json_encode($all_materials) ?>; // Pass PHP materials array to JS

// JavaScript to toggle the Add Daily Report form overlay
function toggleAddReportForm() {
    const formOverlay = document.getElementById('addReportFormOverlay');
    if (formOverlay.style.display === 'flex') {
        formOverlay.style.display = 'none';
        // Clear form fields when closing, if desired
        formOverlay.querySelector('form').reset(); // Resets all form fields
        // Reset current time values as well to always show current on open
        document.getElementById('report_date').value = new Date().toISOString().split('T')[0]; // Reset date to today
        document.getElementById('start_time').value = new Date().toTimeString().slice(0, 5);
        document.getElementById('end_time').value = new Date().toTimeString().slice(0, 5);

        // Clear dynamic material rows except the first one
        const materialsContainer = document.getElementById('materials-input-container');
        while (materialsContainer.children.length > 1) {
            materialsContainer.removeChild(materialsContainer.lastChild);
        }
        // Reset the first row's select and unit display
        const firstSelect = document.getElementById('material_id_0');
        const firstQtyInput = document.getElementById('quantity_used_0');
        const firstUnit = document.getElementById('unit_0');
        if (firstSelect) firstSelect.value = '';
        if (firstQtyInput) firstQtyInput.value = ''; // Clear quantity input
        if (firstUnit) firstUnit.textContent = 'Unit';

        materialRowCounter = 0; // Reset counter
    } else {
        formOverlay.style.display = 'flex';
        // Optional: pre-fill date to today, time to current on open
        document.getElementById('report_date').value = new Date().toISOString().split('T')[0]; // Set date to today
        document.getElementById('start_time').value = new Date().toTimeString().slice(0, 5);
        document.getElementById('end_time').value = new Date().toTimeString().slice(0, 5);
    }
}

// Function to update the unit of measurement dynamically
function updateUnitOfMeasurement(selectElement, unitSpanId) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const unitSpan = document.getElementById(unitSpanId);
    if (selectedOption && selectedOption.dataset.unit) {
        unitSpan.textContent = selectedOption.dataset.unit;
    } else {
        unitSpan.textContent = 'Unit'; // Default text if no unit found or no material selected
    }
}

// Function to add a new material input row
function addMaterialRow() {
    materialRowCounter++;
    const container = document.getElementById('materials-input-container');
    const newRow = document.createElement('div');
    newRow.classList.add('material-input-row');
    newRow.innerHTML = `
        <div class="form-group" style="flex: 1;">
            <label for="material_id_${materialRowCounter}">Material Name:</label>
            <select name="material_ids[]" id="material_id_${materialRowCounter}" onchange="updateUnitOfMeasurement(this, 'unit_${materialRowCounter}')">
                <option value="">-- Select Material --</option>
                ${allMaterials.map(mat => `<option value="${htmlspecialchars(mat.id)}" data-unit="${htmlspecialchars(mat.unit_of_measurement)}">${htmlspecialchars(mat.name)}</option>`).join('')}
            </select>
        </div>
        <div class="form-group qty">
            <label for="quantity_used_${materialRowCounter}">Qty Used:</label>
            <input type="number" step="1" min="1" name="quantities_used[]" id="quantity_used_${materialRowCounter}">
        </div>
        <div class="form-group unit">
            <span id="unit_${materialRowCounter}">Unit</span>
        </div>
        <button type="button" class="remove-material-btn" onclick="removeMaterialRow(this)">X</button>
    `;
    container.appendChild(newRow);
    // Trigger change event on the newly added select to set its initial unit
    newRow.querySelector(`select#material_id_${materialRowCounter}`).dispatchEvent(new Event('change'));
    console.log('Material row added. Current counter:', materialRowCounter); // Debugging
}

// Function to remove a material input row
function removeMaterialRow(buttonElement) {
    buttonElement.closest('.material-input-row').remove();
}

// NEW JavaScript to toggle the Add Checklist Item form overlay
function toggleAddChecklistForm() {
    const formOverlay = document.getElementById('addChecklistFormOverlay');
    if (formOverlay.style.display === 'flex') {
        formOverlay.style.display = 'none';
        formOverlay.querySelector('form').reset(); // Clear form fields
    } else {
        formOverlay.style.display = 'flex';
    }
}

// NEW JavaScript to handle checkbox changes (AJAX for completion status)
function toggleChecklistItem(checkbox) {
    const checklistId = checkbox.dataset.checklistId;
    const isCompleted = checkbox.checked;
    const projectId = <?= $project_id ?>; // Get project_id from PHP

    // Prepare data to send
    const formData = new FormData();
    formData.append('checklist_id', checklistId);
    formData.append('is_completed', isCompleted ? 1 : 0); // Send 1 for checked, 0 for unchecked
    formData.append('project_id', projectId); // Send project_id for redirection

    fetch('process_toggle_checklist_item.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload the page to show updated percentage and status
            window.location.href = `view_project.php?id=${projectId}&status=checklist_item_updated_success`;
        } else {
            alert('Error updating checklist item: ' + data.message);
            checkbox.checked = !isCompleted; // Revert checkbox state on error
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the checklist item.');
        checkbox.checked = !isCompleted; // Revert checkbox state on error
    });
}

// REMOVED: deductMaterial() function as deduction UI is no longer in view_project.php


// NEW: JavaScript to make alert messages disappear after a few seconds
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade-out'); // Add fade-out class
            // After transition, remove element from DOM
            alert.addEventListener('transitionend', () => {
                alert.remove();
            });
        }, 5000); // 5000 milliseconds = 5 seconds
    });

    // Initialize the unit of measurement for the first material row
    const firstMaterialSelect = document.getElementById('material_id_0');
    if (firstMaterialSelect) {
        // Only trigger change if a material is actually selected (not the default "-- Select Material --")
        if (firstMaterialSelect.value !== "") {
             firstMaterialSelect.dispatchEvent(new Event('change'));
        }
    }
});
</script>
</body>
</html>