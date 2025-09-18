<?php
/**
 * Generates a detailed PDF project report for a specific project.
 *
 * This script fetches comprehensive details including project information,
 * completion milestones, acquired materials, and daily development reports
 * from the database. It then compiles this data into a well-formatted
 * PDF document using the Dompdf library.
 *
 * Security: Requires the user to be logged in and have administrative privileges.
 * Input: Expects a 'id' GET parameter representing the project_id.
 *
 * @version 1.6
 * @author Your Name/Team
 */

session_start();
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// --- Includes and Initial Setup ---
include 'includes/db.php';
include 'includes/functions.php'; // Assumed to contain is_admin() and other session helpers

// Set the default timezone for consistent date/time formatting
date_default_timezone_set('Asia/Manila');

// --- Security Check: Ensure user is logged in and has admin privileges ---
if (!isset($_SESSION['user_id']) || !is_admin()) {
    $_SESSION['status_message'] = '<div class="alert error">Unauthorized access to generate reports.</div>';
    header('Location: dashboard.php'); // Redirect to dashboard if not authorized
    exit();
}

// --- Input Validation: Retrieve and validate the project ID from GET parameter ---
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($project_id === 0) {
    $_SESSION['status_message'] = '<div class="alert error">Invalid Project ID provided for PDF generation.</div>';
    header('Location: full_reports.php'); // Redirect if no valid ID
    exit();
}

// --- Data Fetching: Retrieve all necessary project-related data from the database ---

/**
 * Fetch Project Details
 * Retrieves main project information and the name of the assigned constructor.
 */
$stmt_project = $conn->prepare(
    "SELECT p.*, u.username AS constructor_name
     FROM projects p
     LEFT JOIN users u ON p.constructor_id = u.id
     WHERE p.id = ?"
);
$stmt_project->bind_param("i", $project_id);
$stmt_project->execute();
$current_project_details = $stmt_project->get_result()->fetch_assoc();
$stmt_project->close();

// Terminate if project details cannot be found
if (!$current_project_details) {
    $_SESSION['status_message'] = '<div class="alert error">Project not found for PDF generation.</div>';
    header('Location: full_reports.php');
    exit();
}

/**
 * Fetch Checklist Items for THIS Project
 * Gathers all checklist items, their completion status, and who completed them.
 */
$checklist_data = [];
$total_checklist_items = 0;
$completed_checklist_items = 0;

$stmt_checklist = $conn->prepare(
    "SELECT pc.*, u.username AS completed_by_username
     FROM project_checklists pc
     LEFT JOIN users u ON pc.completed_by_user_id = u.id
     WHERE pc.project_id = ? ORDER BY pc.created_at ASC"
);
$stmt_checklist->bind_param("i", $project_id);
$stmt_checklist->execute();
$result_checklist = $stmt_checklist->get_result();
while ($item = $result_checklist->fetch_assoc()) {
    $checklist_data[] = $item;
    if ($item['is_completed']) {
        $completed_checklist_items++;
    }
}
$total_checklist_items = $result_checklist->num_rows; // Correctly get total items
$stmt_checklist->close();

// Calculate project completion percentage
$project_completion_percentage = ($total_checklist_items > 0)
    ? round(($completed_checklist_items / $total_checklist_items) * 100)
    : 0;

/**
 * Fetch Materials Acquired for THIS Project
 * Retrieves a list of materials specifically acquired or allocated to this project.
 */
$materials_for_project_data = [];
$stmt_project_materials = $conn->prepare("SELECT * FROM materials WHERE project_id = ? ORDER BY name ASC");
$stmt_project_materials->bind_param("i", $project_id);
$stmt_project_materials->execute();
$result_project_materials = $stmt_project_materials->get_result();
while ($mat = $result_project_materials->fetch_assoc()) {
    $materials_for_project_data[] = $mat;
}
$stmt_project_materials->close();

/**
 * Fetch Daily Development Reports for THIS Project
 * Collects all construction reports, including reporter information.
 */
$reports_for_project_data = [];
$stmt_project_reports = $conn->prepare(
    "SELECT cr.*, u.username as reporter_name
     FROM construction_reports cr
     LEFT JOIN users u ON cr.constructor_id = u.id
     WHERE cr.project_id = ? ORDER BY cr.report_date DESC, cr.start_time DESC"
);
$stmt_project_reports->bind_param("i", $project_id);
$stmt_project_reports->execute();
$result_project_reports = $stmt_project_reports->get_result();
while ($report_row = $result_project_reports->fetch_assoc()) {
    $reports_for_project_data[] = $report_row;
}
$stmt_project_reports->close();

// --- Dynamic Base URL Configuration ---
// This ensures images load correctly whether on localhost or a live server.
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$base_url = "{$protocol}://{$_SERVER['HTTP_HOST']}/capstone"; // IMPORTANT: Adjust '/capstone' if your project is in a different web server subfolder

// --- Prepare Data for HTML Output (Sanitization for safety) ---
$page_title = 'Project Report - ' . htmlspecialchars($current_project_details['name']);
$project_name_safe = htmlspecialchars($current_project_details['name']);
$location_safe = htmlspecialchars($current_project_details['location']);
$constructor_name_safe = htmlspecialchars($current_project_details['constructor_name']);
$date_created_formatted = date('M d, Y', strtotime($current_project_details['created_at']));
$status_safe = htmlspecialchars($current_project_details['status']);
$logo_url = "{$base_url}/images/Sunshine Sapphire Construction and Supply Logo.png";


// --- HTML & CSS Content for PDF using HEREDOC syntax ---

// Define CSS styles
$css = <<<CSS
    @page { 
        margin: 20mm; /* Consistent page margins */
    }
    body { 
        font-family: "DejaVu Sans", sans-serif; /* Recommended for better Unicode support in Dompdf */
        font-size: 11pt; /* Base font size */
        color: #333; /* Standard text color */
        line-height: 1.5; /* Good readability */
    }
    h1, h2, h3 { 
        color: #000; /* Bolder, black headings for emphasis */
        margin-bottom: 10px; 
        line-height: 1.2; 
        font-weight: bold;
    }
    h1 { font-size: 24pt; margin: 0; } /* Main title size, reset margin */
    h2 { 
        font-size: 16pt; 
        border-bottom: 1px solid #ccc; /* Sub-section heading separator */
        padding-bottom: 8px; 
        margin-top: 35px; /* Space above sub-sections */
    }
    h3 { font-size: 12pt; color: #333; margin-top: 25px; } /* Sub-sub-section headings */
    table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-bottom: 15px; /* Space below tables */
    }
    th, td { 
        border: 1px solid #ddd; 
        padding: 8px; 
        text-align: left; 
        vertical-align: top; 
        word-wrap: break-word; /* Prevents long words from breaking layout */
        font-size: 9.5pt;
    }
    th { 
        background-color: #f9f9f9; 
        font-weight: bold; 
    }
    .text-muted { color: #888; font-style: italic; } /* For less important text */
    .text-small { font-size: 9pt; }

    /* --- Header Layout for Logo and Split Title --- */
    .header-table {
        width: 100%;
        margin-bottom: 40px; /* Space after the main header block */
        border: none; /* No borders for the header table itself */
    }
    .header-table td {
        border: none;
        padding: 0;
        vertical-align: middle; /* Aligns logo and title vertically */
    }
    .logo-cell {
        width: 150px; /* Fixed width for the logo column */
        padding-right: 20px; /* Space between logo and title text */
    }
    .logo-cell img {
        max-width: 110px; /* Max size for the logo image */
        height: auto;
        display: block; /* Helps with alignment and margin */
    }
    .title-cell {
        text-align: left; /* Align the title block to the left */
    }
    .report-title-container {
        display: block; 
        line-height: 1.1; /* Tighter line spacing for the title */
    }
    .report-title-prefix {
        font-size: 16pt; /* Smaller font for "Project Report:" */
        font-weight: normal; 
        display: block; /* Ensures it's on its own line */
        margin-bottom: 5px; /* Space between prefix and project name */
    }
    .report-project-name {
        font-size: 22pt; /* Adjusted: Larger, emphasized font for the project name */
        font-weight: bold;
        display: block; /* Ensures it's on its own line */
        white-space: nowrap; /* Tries to keep the name on one line */
        overflow: hidden; /* Hides overflowing text */
        text-overflow: ellipsis; /* Adds "..." if text is truncated */
        max-width: 100%; /* Important for overflow to work correctly */
    }
    
    /* Project Details Block Styling */
    .project-details p { 
        margin: 4px 0; 
        font-size: 12pt;
    }
    .project-details p strong {
        font-weight: bold;
        color: #000;
    }

    /* Checklist Section Styling */
    .progress-bar-wrapper { 
        background-color: #e0e0e0; 
        border-radius: 5px; 
        height: 12px; 
        width: 100%; 
        margin: 10px 0;
    }
    .progress-bar { 
        height: 100%; 
        background-color: #28a745; /* Green for completion */
        border-radius: 5px;
    }
    .checklist-list { list-style: none; padding-left: 0; margin-top: 15px; }
    .checklist-item { margin-bottom: 5px; font-size: 11pt; }
    .checklist-item.completed { text-decoration: line-through; color: #777; }
    .checklist-item .checkbox { 
        font-size: 14pt; /* Unicode checkbox size */
        vertical-align: middle; 
        margin-right: 8px; 
    }
    .checklist-meta { font-size: 8pt; color: #888; margin-left: 25px; display: block; }

    /* Materials Table Styling */
    .qty-unit-display { text-align: right; }
    .qty-unit-display .quantity { font-weight: bold; }
    .qty-unit-display .unit { font-size: 8pt; color: #777; }
    
    /* Daily Report Proof Image Styling */
    .report-proof-image {
        max-width: 150px; /* Maximum width for the image */
        max-height: 150px; /* Maximum height for the image */
        width: auto; /* Ensures aspect ratio is maintained */
        height: auto; /* Ensures aspect ratio is maintained */
        object-fit: contain; /* Scales image down to fit, without cropping */
        display: block; /* Makes it a block element for margin control */
        margin: 5px 0 10px 0; /* Align left, add space below */
        border: 1px solid #eee; /* Light border */
        padding: 3px; /* Inner padding */
    }
    .material-usage-list { list-style-type: disc; padding-left: 20px; margin: 5px 0; font-size: 9pt; }
    .deducted-status { color: #28a745; font-weight: bold; font-size: 8pt; white-space: nowrap; }

CSS;

// Start building the HTML content
$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$page_title}</title>
    <style>{$css}</style>
</head>
<body>
    <!-- Main Report Header: Logo on left, Split Title on right -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <img src="{$logo_url}" alt="Company Logo">
            </td>
            <td class="title-cell">
                <div class="report-title-container">
                    <span class="report-title-prefix">Project Report:</span>
                    <span class="report-project-name">{$project_name_safe}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Project General Details -->
    <div class="project-details">
        <p><strong>Location:</strong> {$location_safe}</p>
        <p><strong>Assigned Constructor:</strong> {$constructor_name_safe}</p>
        <p><strong>Date Created:</strong> {$date_created_formatted}</p>
        <p><strong>Current Status:</strong> {$status_safe}</p>
    </div>

    <!-- Project Milestones / Checklist Section -->
    <h2>Project Milestones / Checklist</h2>
    <p>Overall Progress: <strong>{$project_completion_percentage}%</strong> ({$completed_checklist_items} of {$total_checklist_items} completed)</p>
HTML;

// Progress bar for checklist
if ($total_checklist_items > 0) {
    $html .= <<<HTML
    <div class="progress-bar-wrapper">
        <div class="progress-bar" style="width: {$project_completion_percentage}%;"></div>
    </div>
HTML;
} else {
    $html .= '<p class="text-muted text-small">No checklist items have been added to this project.</p>';
}

// Detailed checklist items
if (!empty($checklist_data)) {
    $html .= '<ul class="checklist-list">';
    foreach ($checklist_data as $item) {
        $completed_class = $item['is_completed'] ? 'completed' : '';
        $item_description = htmlspecialchars($item['item_description']);
        $checkbox = $item['is_completed'] ? '☑' : '☐'; // Unicode checkboxes

        $html .= "<li class='checklist-item {$completed_class}'><span class='checkbox'>{$checkbox}</span> {$item_description}";
        
        if ($item['is_completed'] && $item['completed_by_username']) {
            $completed_by = htmlspecialchars($item['completed_by_username']);
            $completed_at = date('M d, Y h:i A', strtotime($item['completed_at']));
            $html .= "<span class='checklist-meta'>(Completed by: {$completed_by} on {$completed_at})</span>";
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
}

// --- Materials Acquired for This Project Section ---
$html .= '<h2>Materials Acquired for This Project</h2>';
if (!empty($materials_for_project_data)) {
    $html .= <<<HTML
    <table>
        <thead>
            <tr>
                <th style="width: 35%;">Name</th>
                <th style="width: 15%; text-align: right;">Quantity</th>
                <th style="width: 25%;">Supplier</th>
                <th style="width: 25%; text-align: right;">Total Value</th>
            </tr>
        </thead>
        <tbody>
HTML;
    foreach ($materials_for_project_data as $material) {
        $name = htmlspecialchars($material['name']);
        $quantity = htmlspecialchars(number_format($material['quantity'], 0));
        $unit = htmlspecialchars($material['unit_of_measurement']);
        $supplier = htmlspecialchars($material['supplier']);
        $amount = '₱ ' . htmlspecialchars(number_format($material['total_amount'], 2)); // Assuming peso currency

        $html .= <<<HTML
        <tr>
            <td>{$name}</td>
            <td style="text-align:right;">{$quantity} {$unit}</td>
            <td>{$supplier}</td>
            <td style="text-align: right;">{$amount}</td>
        </tr>
HTML;
    }
    $html .= '</tbody></table>';
} else {
    $html .= '<p class="text-muted text-small">No materials have been recorded for this project.</p>';
}

// --- Daily Development Reports for THIS Project Section ---
$html .= '<h2>Daily Development Reports</h2>';
if (!empty($reports_for_project_data)) {
    foreach ($reports_for_project_data as $report) {
        $report_date = date('F d, Y', strtotime($report['report_date']));
        $time_range = date('h:i A', strtotime($report['start_time'])) . ' - ' . date('h:i A', strtotime($report['end_time']));
        $status = htmlspecialchars($report['status']);
        $reporter = htmlspecialchars($report['reporter_name']);
        $description = nl2br(htmlspecialchars($report['description'])); // nl2br converts newlines to <br>

        $html .= "<h3>Report for {$report_date}</h3>";
        $html .= "<p class='text-small'><strong>Time:</strong> {$time_range} | <strong>Status:</strong> {$status} | <strong>Reporter:</strong> {$reporter}</p>";
        $html .= "<p><strong>Activities:</strong><br>{$description}</p>";

        // Fetch material usages for this specific report
        $material_usages = [];
        $stmt_usage = $conn->prepare(
            "SELECT rmu.quantity_used, rmu.is_deducted, rmu.deducted_at,
                    m.name AS material_name, m.unit_of_measurement, u.username AS deducted_by_username
             FROM report_material_usage rmu
             JOIN materials m ON rmu.material_id = m.id
             LEFT JOIN users u ON rmu.deducted_by_user_id = u.id
             WHERE rmu.report_id = ?"
        );
        $stmt_usage->bind_param("i", $report['id']);
        $stmt_usage->execute();
        $usage_result = $stmt_usage->get_result();
        while ($usage_row = $usage_result->fetch_assoc()) {
            $material_usages[] = $usage_row;
        }
        $stmt_usage->close();

        if (!empty($material_usages)) {
            $html .= '<p><strong>Materials Reported Used:</strong></p>';
            $html .= '<ul class="material-usage-list">';
            foreach ($material_usages as $usage) {
                $html .= '<li>' . htmlspecialchars(number_format($usage['quantity_used'], 0)) . ' ' . htmlspecialchars($usage['unit_of_measurement']) . ' of ' . htmlspecialchars($usage['material_name']);
                if ($usage['is_deducted']) {
                     $html .= ' <span class="deducted-status">(Deducted';
                     if ($usage['deducted_by_username'] && $usage['deducted_at']) {
                         $html .= ' by ' . htmlspecialchars($usage['deducted_by_username']) . ' on ' . date('m-d-Y h:i A', strtotime($usage['deducted_at']));
                     }
                     $html .= ')</span>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p class="text-small text-muted">No materials reported used for this day.</p>';
        }

        // Proof Image display
        if (!empty($report['proof_image'])) {
            $image_src = "{$base_url}/" . htmlspecialchars($report['proof_image']); // Full path to image
            $html .= '<p><strong>Proof Image:</strong><br><img src="' . $image_src . '" class="report-proof-image" alt="Proof"></p>';
        } else {
            $html .= '<p class="text-small text-muted">No proof image uploaded.</p>';
        }
        $html .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">'; // Separator for individual reports
    }
} else {
    $html .= '<p class="text-muted text-small">No daily development reports found for this project.</p>';
}

$html .= '</body></html>';


// --- Dompdf Configuration and Output ---
$options = new Options();
$options->set('isHtml5ParserEnabled', true); // Enable HTML5 parsing
$options->set('isRemoteEnabled', true);     // IMPORTANT: Allows Dompdf to fetch external resources like images (from $base_url)
$options->set('defaultFont', 'DejaVu Sans'); // Set a font that supports a wider range of characters

// Optional: Set a temporary directory if default causes issues (ensure it exists and is writable)
// $options->set('tempDir', __DIR__ . '/tmp'); 
// if (!is_dir(__DIR__ . '/tmp')) {
//     mkdir(__DIR__ . '/tmp', 0777, true);
// }

// Initialize Dompdf with the configured options
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Set paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render the HTML as PDF
$dompdf->render();

// Generate a clean filename for the PDF download
$filename_safe_project_name = preg_replace('/[^a-zA-Z0-9-_\.]/','_', $current_project_details['name']);
$filename = "Project_Report_{$filename_safe_project_name}_" . date('Y-m-d') . ".pdf";

// Output the generated PDF to the browser (Attachment => false means open in browser)
$dompdf->stream($filename, ["Attachment" => false]);

exit(); // Terminate script execution after PDF is streamed