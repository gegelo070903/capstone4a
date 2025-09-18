<?php
session_start();
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

include 'includes/db.php';
include 'includes/functions.php'; // For is_admin() and session checks

date_default_timezone_set('Asia/Manila');

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !is_admin()) {
    $_SESSION['status_message'] = '<div class="alert error">Unauthorized access to generate reports.</div>';
    header('Location: dashboard.php');
    exit();
}

// --- Fetch ALL Projects ---
$all_projects_data = [];
$stmt_all_projects = $conn->prepare("SELECT id, name, location, created_at, status, constructor_id FROM projects ORDER BY name ASC");
$stmt_all_projects->execute();
$result_all_projects = $stmt_all_projects->get_result();
while ($proj = $result_all_projects->fetch_assoc()) {
    $all_projects_data[] = $proj;
}
$stmt_all_projects->close();

$html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Full System Project Reports</title>
    <style>
        @page { margin: 15mm 20mm; } /* Define page margins */
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #333; line-height: 1.4; padding-top: 0; }
        h1, h2, h3, h4 { color: #333; margin-bottom: 8px; line-height: 1.2; }
        h1 { font-size: 18pt; margin: 0; } /* Reset H1 margins */
        h2 { font-size: 14pt; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-top: 25px; }
        h3 { font-size: 12pt; color: #555; margin-top: 20px; }
        h4 { font-size: 11pt; color: #666; margin-top: 15px; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; table-layout: fixed; }
        th, td { border: 1px solid #eee; padding: 6px 8px; text-align: left; vertical-align: top; word-wrap: break-word; font-size: 9pt; line-height: 1.2; }
        th { background-color: #f8f8f8; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-muted { color: #888; font-style: italic; }
        .text-small { font-size: 8pt; }
        .page-break { page-break-before: always; }

        /* New Header Layout for Logo and Title */
        .report-header-block {
            margin-top: 10mm; /* Overall top margin for the header block */
            margin-bottom: 20px; /* Space below the entire header block */
            overflow: auto; /* Clearfix for floated elements */
            height: auto; /* Allow height to adjust to content */
        }
        .company-logo-container {
            float: left; /* Float the logo to the left */
            width: 120px; /* Space reserved for the logo */
            margin-right: 15px; /* Space between logo and title */
            margin-left: 0; /* Align with page margin */
            padding-top: 0; /* Reset internal padding */
            line-height: 1; /* Reset line-height */
        }
        .company-logo-container img {
            width: 100px; /* Logo size */
            height: auto;
            display: block; /* Ensure it behaves as a block */
            margin: 0; /* Reset img margins */
        }
        .project-title-container {
            overflow: hidden; /* Contains the floated logo */
            padding-top: 15px; /* Adjust this to push title down relative to logo */
            text-align: left;
        }
        .project-title-container h1 {
            margin: 0; /* Remove default h1 margins */
            font-size: 18pt;
            line-height: 1.1;
        }

        /* Project Details */
        .project-details-block { margin-bottom: 20px; margin-top: 15px; }
        .project-details-block p { margin: 2px 0; }

        /* Checklist */
        .progress-bar-wrapper { background-color: #e0e0e0; border-radius: 10px; height: 10px; width: 100%; margin-top: 5px; overflow: hidden; position: relative; }
        .progress-bar { height: 100%; background-color: #28a745; text-align: center; color: white; line-height: 10px; font-size: 7pt; }
        .checklist-item { margin-bottom: 3px; font-size: 9pt; line-height: 1.3; }
        .checklist-item.completed { text-decoration: line-through; color: #777; }
        .checklist-item-checkbox { width: 12px; height: 12px; vertical-align: middle; margin-right: 5px; }
        .checklist-meta { font-size: 7pt; color: #888; margin-left: 18px; display: block; }

        /* Materials Table */
        .qty-unit-display { display: block; text-align: right; }
        .qty-unit-display .quantity-value { font-weight: bold; color: #333; display: block; }
        .qty-unit-display .unit-value { font-size: 8pt; color: #777; display: block; margin-top: -2px; }

        /* Reports Table */
        .report-proof-image { max-width: 80px; max-height: 80px; margin-top: 5px; border: 1px solid #eee; padding: 2px; }
        .material-usage-list { list-style: none; padding: 0; margin: 0; }
        .material-usage-list li { margin-bottom: 2px; border-bottom: 1px dotted #eee; padding-bottom: 2px; font-size: 8pt; }
        .material-usage-list li:last-child { border-bottom: none; }
        .material-usage-list .quantity-display { font-weight: bold; color: #333; }
        .material-usage-list .material-name { margin-left: 5px; }
        .deducted-status { color: #28a745; font-weight: bold; font-size: 8pt; white-space: nowrap; margin-left: 5px; }
        .deducted-status i { margin-left: 3px; }
        .deducted-info { font-size: 7pt; color: #888; display: block; margin-top: 1px; line-height: 1.1; }

    </style>
</head>
<body>';

$first_project = true;

foreach ($all_projects_data as $project_summary) {
    if (!$first_project) {
        $html .= '<div class="page-break"></div>'; // Start new project on a new page
    }
    $first_project = false;

    // --- Header Area with Logo and Title ---
    $logo_url = 'http://localhost/capstone/images/Sunshine Sapphire Construction and Supply Logo.png'; // ADJUST THIS BASE URL
    $html .= '<div class="report-header-block">';
    $html .= '<div class="company-logo-container">';
    $html .= '<img src="' . htmlspecialchars($logo_url) . '" alt="Company Logo">';
    $html .= '</div>'; // End .company-logo-container
    $html .= '<div class="project-title-container">';
    $html .= '<h1>Project Report: ' . htmlspecialchars($project_summary['name']) . '</h1>';
    $html .= '</div>'; // End .project-title-container
    $html .= '</div>'; // End .report-header-block

    // --- Fetch full project details (constructor name) for this specific project ---
    $current_project_details = null;
    $stmt_current_project = $conn->prepare("SELECT p.*, u.username AS constructor_name
                                            FROM projects p
                                            LEFT JOIN users u ON p.constructor_id = u.id
                                            WHERE p.id = ?");
    $stmt_current_project->bind_param("i", $project_summary['id']);
    $stmt_current_project->execute();
    $result_current_project = $stmt_current_project->get_result();
    $current_project_details = $result_current_project->fetch_assoc();
    $stmt_current_project->close();


    $html .= '<div class="project-details-block">
                <p><strong>Location:</strong> ' . htmlspecialchars($current_project_details['location']) . '</p>
                <p><strong>Assigned Constructor:</strong> ' . htmlspecialchars($current_project_details['constructor_name']) . '</p>
                <p><strong>Date Created:</strong> ' . date('M d, Y', strtotime($current_project_details['created_at'])) . '</p>
                <p><strong>Current Status:</strong> ' . htmlspecialchars($current_project_details['status']) . '</p>
              </div>';

    // --- Fetch Checklist Items for THIS Project ---
    $checklist_data = [];
    $total_checklist_items = 0;
    $completed_checklist_items = 0;

    $stmt_checklist = $conn->prepare("SELECT pc.*, u.username AS completed_by_username
                                     FROM project_checklists pc
                                     LEFT JOIN users u ON pc.completed_by_user_id = u.id
                                     WHERE pc.project_id = ? ORDER BY pc.created_at ASC");
    $stmt_checklist->bind_param("i", $project_summary['id']);
    $stmt_checklist->execute();
    $result_checklist = $stmt_checklist->get_result();
    while ($item = $result_checklist->fetch_assoc()) {
        $total_checklist_items++;
        if ($item['is_completed']) {
            $completed_checklist_items++;
        }
        $checklist_data[] = $item;
    }
    $stmt_checklist->close();

    $project_completion_percentage = ($total_checklist_items > 0)
                                     ? round(($completed_checklist_items / $total_checklist_items) * 100)
                                     : 0;

    $html .= '<h2>Project Milestones / Checklist</h2>';
    $html .= '<p>Overall Progress: ' . $project_completion_percentage . '% (' . $completed_checklist_items . ' of ' . $total_checklist_items . ' completed)</p>';
    if ($total_checklist_items > 0) { // Only show progress bar if there are items
        $html .= '<div class="progress-bar-wrapper" style="width: 100%;"><div class="progress-bar" style="width: ' . $project_completion_percentage . '%;"></div></div>';
    } else {
        $html .= '<p class="text-muted text-small">No checklist items added for this project.</p>';
    }

    if (!empty($checklist_data)) {
        $html .= '<ul style="list-style: none; padding: 0; margin-top: 10px;">';
        foreach ($checklist_data as $item) {
            $html .= '<li class="checklist-item ' . ($item['is_completed'] ? 'completed' : '') . '">';
            // Using SVG directly for checkboxes
            $fill_color = $item['is_completed'] ? '#28a745' : '#ccc';
            $path_d = $item['is_completed'] ? 'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z' : 'M19 5v14H5V5h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z';
            $html .= '<img src="data:image/svg+xml;base64,' . base64_encode('<svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="' . $path_d . '" fill="' . $fill_color . '"></path></svg>') . '" class="checklist-item-checkbox" alt="checkbox">';
            $html .= htmlspecialchars($item['item_description']);
            if ($item['is_completed'] && $item['completed_by_username']) {
                $html .= '<span class="checklist-meta"> (Completed by: ' . htmlspecialchars($item['completed_by_username']) . ' on ' . date('M d, Y h:i A', strtotime($item['completed_at'])) . ')</span>';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';
    } else {
        // Already handled above if no items
    }


    // --- Fetch Materials for THIS Project ---
    $materials_for_project_data = [];
    $stmt_project_materials = $conn->prepare("SELECT * FROM materials WHERE project_id = ? ORDER BY name ASC");
    $stmt_project_materials->bind_param("i", $project_summary['id']);
    $stmt_project_materials->execute();
    $result_project_materials = $stmt_project_materials->get_result();
    while ($mat = $result_project_materials->fetch_assoc()) {
        $materials_for_project_data[] = $mat;
    }
    $stmt_project_materials->close();

    $html .= '<h2>Materials Acquired for This Project</h2>';
    if (!empty($materials_for_project_data)) {
        $html .= '<table>
                    <thead>
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 18%;">Name</th>
                            <th style="width: 10%; text-align: right;">Quantity</th>
                            <th style="width: 18%;">Supplier</th>
                            <th style="width: 10%; text-align: right;">Total Value</th>
                            <th style="width: 15%;">Date Added</th>
                            <th style="width: 24%;">Purpose</th>
                        </tr>
                    </thead>
                    <tbody>';
        foreach ($materials_for_project_data as $material) {
            $material_date_str = $material['date'];
            $material_time_str = $material['time'];
            $formatted_date_time = 'N/A';
            if ($material_date_str !== '0000-00-00' && $material_date_str !== null) {
                $material_date_timestamp = strtotime($material_date_str);
                $formatted_date = date('M d, Y', $material_date_timestamp);
                $formatted_time = ($material_time_str) ? date('h:i A', strtotime($material_time_str)) : '';
                $formatted_date_time = $formatted_date;
                if (!empty($formatted_time) && $formatted_time !== '12:00 AM') {
                    $formatted_date_time .= ' (' . $formatted_time . ')';
                }
            }

            $html .= '<tr>
                        <td style="text-align: center;">' . htmlspecialchars($material['id']) . '</td>
                        <td>' . htmlspecialchars($material['name']) . '</td>
                        <td style="text-align: right;"><div class="qty-unit-display"><span class="quantity-value">' . htmlspecialchars(number_format($material['quantity'], 0)) . '</span><span class="unit-value">' . htmlspecialchars($material['unit_of_measurement']) . '</span></div></td>
                        <td>' . htmlspecialchars($material['supplier']) . '</td>
                        <td style="text-align: right;">₱ ' . htmlspecialchars(number_format($material['total_amount'], 0)) . '</td>
                        <td>' . $formatted_date_time . '</td>
                        <td>' . htmlspecialchars($material['purpose']) . '</td>
                      </tr>';
        }
        $html .= '</tbody></table>';
    } else {
        $html .= '<p class="text-muted text-small">No materials specifically acquired for this project.</p>';
    }

    // --- Fetch Daily Development Reports for THIS Project ---
    $reports_for_project_data = [];
    $stmt_project_reports = $conn->prepare("SELECT cr.*, u.username as reporter_name
                                            FROM construction_reports cr
                                            LEFT JOIN users u ON cr.constructor_id = u.id
                                            WHERE cr.project_id = ? ORDER BY cr.report_date DESC, cr.start_time DESC");
    $stmt_project_reports->bind_param("i", $project_summary['id']);
    $stmt_project_reports->execute();
    $result_project_reports = $stmt_project_reports->get_result();
    while ($report_row = $result_project_reports->fetch_assoc()) {
        $reports_for_project_data[] = $report_row;
    }
    $stmt_project_reports->close();

    $html .= '<h2>Daily Development Reports for This Project</h2>';
    if (!empty($reports_for_project_data)) {
        foreach ($reports_for_project_data as $report) {
            $html .= '<h3>Report on ' . date('M d, Y', strtotime($report['report_date'])) . '</h3>';
            $html .= '<p class="text-small"><strong>Time:</strong> ' . date('h:i A', strtotime($report['start_time'])) . ' - ' . date('h:i A', strtotime($report['end_time'])) . '</p>';
            $html .= '<p class="text-small"><strong>Status:</strong> ' . htmlspecialchars($report['status']) . '</p>';
            $html .= '<p class="text-small"><strong>Reporter:</strong> ' . htmlspecialchars($report['reporter_name']) . '</p>';
            $html .= '<p><strong>Description:</strong> ' . nl2br(htmlspecialchars($report['description'])) . '</p>';

            // Fetch material usages for this specific report
            $material_usages = [];
            $stmt_usage = $conn->prepare("SELECT rmu.id AS rmu_id, rmu.quantity_used, rmu.is_deducted, rmu.deducted_at,
                                                 m.name AS material_name, m.unit_of_measurement, u.username AS deducted_by_username
                                          FROM report_material_usage rmu
                                          JOIN materials m ON rmu.material_id = m.id
                                          LEFT JOIN users u ON rmu.deducted_by_user_id = u.id
                                          WHERE rmu.report_id = ?");
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
                         $html .= ' <span class="deducted-status" style="color:#28a745;">(Deducted';
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

            if (!empty($report['proof_image'])) {
                $image_src = 'http://localhost/capstone/' . htmlspecialchars($report['proof_image']); // ADJUST THIS BASE URL
                $html .= '<p><strong>Proof Image:</strong><br><img src="' . $image_src . '" class="report-proof-image" alt="Proof Image"></p>';
            } else {
                $html .= '<p class="text-small text-muted">No proof image uploaded.</p>';
            }
            $html .= '<hr style="margin: 15px 0;">'; // Separator for individual reports
        }
    } else {
        $html .= '<p class="text-muted text-small">No daily development reports for this project.</p>';
    }

} // End foreach project loop

$html .= '</body></html>';


// --- Dompdf Configuration and Output ---
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // Allow loading external resources like images (crucial for local base URLs)
$options->set('defaultFont', 'DejaVu Sans'); // Use DejaVu Sans for better Unicode support
$options->set('tempDir', __DIR__ . '/tmp'); // Ensure this directory exists and is writable by the web server

// Create the temporary directory if it's not present and writable
if (!is_dir(__DIR__ . '/tmp')) {
    mkdir(__DIR__ . '/tmp', 0777, true); // Attempt to create with full permissions
    // Even after creating, permissions might still be an issue depending on OS setup
}


$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

$dompdf->setPaper('A4', 'portrait');

// Render the HTML as PDF
$dompdf->render();

// Output the generated PDF to Browser
$dompdf->stream("Full_System_Report_" . date('Y-m-d_His') . ".pdf", array("Attachment" => false));

exit();