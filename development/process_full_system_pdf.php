<?php
session_start();
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

include 'includes/db.php';
include 'includes/functions.php'; // For is_admin() and session checks

date_default_timezone_set('Asia/Manila');

// --------------------------------------------------
// Access control — only admins can generate system PDF
// --------------------------------------------------
if (!isset($_SESSION['user_id']) || !is_admin()) {
    $_SESSION['status_message'] = '<div class="alert error">Unauthorized access to generate reports.</div>';
    header('Location: dashboard.php');
    exit();
}

// --------------------------------------------------
// Base URL detection (auto works for localhost or live server)
// --------------------------------------------------
$base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';

// --------------------------------------------------
// Fetch all projects
// --------------------------------------------------
$all_projects_data = [];
$stmt_all_projects = $conn->prepare("SELECT id, name, location, created_at, status, constructor_id FROM projects ORDER BY name ASC");
$stmt_all_projects->execute();
$result_all_projects = $stmt_all_projects->get_result();
while ($proj = $result_all_projects->fetch_assoc()) {
    $all_projects_data[] = $proj;
}
$stmt_all_projects->close();

// Stop if no projects found
if (empty($all_projects_data)) {
    echo '<p style="font-family:sans-serif;color:#666;text-align:center;">No projects found to include in the report.</p>';
    exit();
}

// --------------------------------------------------
// Begin HTML template for PDF
// --------------------------------------------------
$html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Full System Project Reports</title>
<style>
    @page { margin: 15mm 20mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #333; line-height: 1.4; padding-top: 0; }
    h1, h2, h3, h4 { color: #333; margin-bottom: 8px; line-height: 1.2; }
    h1 { font-size: 18pt; margin: 0; }
    h2 { font-size: 14pt; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-top: 25px; }
    h3 { font-size: 12pt; color: #555; margin-top: 20px; }
    h4 { font-size: 11pt; color: #666; margin-top: 15px; margin-bottom: 5px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; table-layout: fixed; }
    th, td { border: 1px solid #eee; padding: 6px 8px; text-align: left; vertical-align: top; word-wrap: break-word; font-size: 9pt; line-height: 1.2; }
    th { background-color: #f8f8f8; font-weight: bold; }
    .text-muted { color: #888; font-style: italic; }
    .text-small { font-size: 8pt; }
    .page-break { page-break-before: always; }

    /* Header layout */
    .report-header-block { margin-top: 10mm; margin-bottom: 20px; overflow: auto; }
    .company-logo-container { float: left; width: 120px; margin-right: 15px; }
    .company-logo-container img { width: 100px; height: auto; display: block; }
    .project-title-container { overflow: hidden; padding-top: 15px; text-align: left; }
    .project-title-container h1 { margin: 0; font-size: 18pt; line-height: 1.1; }

    /* Project details */
    .project-details-block { margin-bottom: 20px; margin-top: 15px; }
    .project-details-block p { margin: 2px 0; }

    /* Checklist */
    .progress-bar-wrapper { background-color: #e0e0e0; border-radius: 10px; height: 10px; width: 100%; margin-top: 5px; overflow: hidden; }
    .progress-bar { height: 100%; background-color: #28a745; }

    /* Materials & Reports */
    .qty-unit-display { display: block; text-align: right; }
    .qty-unit-display .quantity-value { font-weight: bold; color: #333; display: block; }
    .qty-unit-display .unit-value { font-size: 8pt; color: #777; display: block; margin-top: -2px; }
    .report-proof-image { max-width: 80px; max-height: 80px; margin-top: 5px; border: 1px solid #eee; padding: 2px; }
    .material-usage-list { list-style: none; padding: 0; margin: 0; }
    .material-usage-list li { margin-bottom: 2px; border-bottom: 1px dotted #eee; padding-bottom: 2px; font-size: 8pt; }
    .deducted-status { color: #28a745; font-weight: bold; font-size: 8pt; white-space: nowrap; margin-left: 5px; }
</style>
</head>
<body>';

$first_project = true;

foreach ($all_projects_data as $project_summary) {
    if (!$first_project) {
        $html .= '<div class="page-break"></div>';
    }
    $first_project = false;

    // Header with dynamic logo
    $logo_url = $base_url . 'images/Sunshine Sapphire Construction and Supply Logo.png';
    $html .= '<div class="report-header-block">
                <div class="company-logo-container">
                    <img src="' . htmlspecialchars($logo_url) . '" alt="Company Logo">
                </div>
                <div class="project-title-container">
                    <h1>Project Report: ' . htmlspecialchars($project_summary['name']) . '</h1>
                </div>
              </div>';

    // Fetch full project details
    $stmt_current_project = $conn->prepare("SELECT p.*, u.username AS constructor_name
                                            FROM projects p
                                            LEFT JOIN users u ON p.constructor_id = u.id
                                            WHERE p.id = ?");
    $stmt_current_project->bind_param("i", $project_summary['id']);
    $stmt_current_project->execute();
    $result_current_project = $stmt_current_project->get_result();
    $project = $result_current_project->fetch_assoc();
    $stmt_current_project->close();

    $html .= '<div class="project-details-block">
                <p><strong>Location:</strong> ' . htmlspecialchars($project['location']) . '</p>
                <p><strong>Constructor:</strong> ' . htmlspecialchars($project['constructor_name']) . '</p>
                <p><strong>Created:</strong> ' . date('M d, Y', strtotime($project['created_at'])) . '</p>
                <p><strong>Status:</strong> ' . htmlspecialchars($project['status']) . '</p>
              </div>';

    // Checklist
    $stmt_checklist = $conn->prepare("SELECT pc.*, u.username AS completed_by_username
                                      FROM project_checklists pc
                                      LEFT JOIN users u ON pc.completed_by_user_id = u.id
                                      WHERE pc.project_id = ? ORDER BY pc.created_at ASC");
    $stmt_checklist->bind_param("i", $project_summary['id']);
    $stmt_checklist->execute();
    $result_checklist = $stmt_checklist->get_result();

    $checklist_data = [];
    $total = 0;
    $done = 0;
    while ($item = $result_checklist->fetch_assoc()) {
        $total++;
        if ($item['is_completed']) $done++;
        $checklist_data[] = $item;
    }
    $stmt_checklist->close();

    $percent = $total > 0 ? round(($done / $total) * 100) : 0;

    $html .= '<h2>Project Milestones / Checklist</h2>
              <p>Overall Progress: ' . $percent . '% (' . $done . ' of ' . $total . ' completed)</p>';
    if ($total > 0) {
        $html .= '<div class="progress-bar-wrapper"><div class="progress-bar" style="width:' . $percent . '%;"></div></div>';
    } else {
        $html .= '<p class="text-muted text-small">No checklist items yet.</p>';
    }

    if (!empty($checklist_data)) {
        $html .= '<ul style="list-style:none;padding:0;margin-top:10px;">';
        foreach ($checklist_data as $item) {
            $html .= '<li style="font-size:9pt;' . ($item['is_completed'] ? 'text-decoration:line-through;color:#777;' : '') . '">'
                   . htmlspecialchars($item['item_description']);
            if ($item['is_completed'] && $item['completed_by_username']) {
                $html .= '<span class="text-small text-muted"> (by ' . htmlspecialchars($item['completed_by_username']) . ' on '
                       . date('M d, Y h:i A', strtotime($item['completed_at'])) . ')</span>';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';
    }

    // Materials
    $stmt_mat = $conn->prepare("SELECT * FROM materials WHERE project_id = ? ORDER BY name ASC");
    $stmt_mat->bind_param("i", $project_summary['id']);
    $stmt_mat->execute();
    $materials = $stmt_mat->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_mat->close();

    $html .= '<h2>Materials Acquired</h2>';
    if ($materials) {
        $html .= '<table><thead><tr>
                    <th>ID</th><th>Name</th><th>Qty</th><th>Supplier</th><th>Total</th><th>Date</th><th>Purpose</th>
                  </tr></thead><tbody>';
        foreach ($materials as $m) {
            $date = $m['date'] !== '0000-00-00' ? date('M d, Y', strtotime($m['date'])) : 'N/A';
            $html .= '<tr>
                        <td>' . $m['id'] . '</td>
                        <td>' . htmlspecialchars($m['name']) . '</td>
                        <td class="text-right">' . htmlspecialchars(number_format($m['quantity'], 0)) . ' ' . htmlspecialchars($m['unit_of_measurement']) . '</td>
                        <td>' . htmlspecialchars($m['supplier']) . '</td>
                        <td class="text-right">₱ ' . number_format($m['total_amount'], 0) . '</td>
                        <td>' . $date . '</td>
                        <td>' . htmlspecialchars($m['purpose']) . '</td>
                      </tr>';
        }
        $html .= '</tbody></table>';
    } else {
        $html .= '<p class="text-muted text-small">No materials found.</p>';
    }

    // Reports
    $stmt_reports = $conn->prepare("SELECT cr.*, u.username AS reporter_name
                                    FROM construction_reports cr
                                    LEFT JOIN users u ON cr.constructor_id = u.id
                                    WHERE cr.project_id = ? ORDER BY cr.report_date DESC, cr.start_time DESC");
    $stmt_reports->bind_param("i", $project_summary['id']);
    $stmt_reports->execute();
    $reports = $stmt_reports->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_reports->close();

    $html .= '<h2>Daily Development Reports</h2>';
    if ($reports) {
        foreach ($reports as $r) {
            $html .= '<h3>Report on ' . date('M d, Y', strtotime($r['report_date'])) . '</h3>
                      <p class="text-small"><strong>Time:</strong> ' . date('h:i A', strtotime($r['start_time'])) . ' - ' . date('h:i A', strtotime($r['end_time'])) . '</p>
                      <p class="text-small"><strong>Status:</strong> ' . htmlspecialchars($r['status']) . '</p>
                      <p class="text-small"><strong>Reporter:</strong> ' . htmlspecialchars($r['reporter_name']) . '</p>
                      <p><strong>Description:</strong> ' . nl2br(htmlspecialchars($r['description'])) . '</p>';

            // Material usage for this report
            $stmt_usage = $conn->prepare("SELECT rmu.quantity_used, rmu.is_deducted, rmu.deducted_at,
                                                 m.name AS material_name, m.unit_of_measurement, u.username AS deducted_by_username
                                          FROM report_material_usage rmu
                                          JOIN materials m ON rmu.material_id = m.id
                                          LEFT JOIN users u ON rmu.deducted_by_user_id = u.id
                                          WHERE rmu.report_id = ?");
            $stmt_usage->bind_param("i", $r['id']);
            $stmt_usage->execute();
            $usages = $stmt_usage->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_usage->close();

            if ($usages) {
                $html .= '<p><strong>Materials Used:</strong></p><ul class="material-usage-list">';
                foreach ($usages as $u) {
                    $html .= '<li>' . htmlspecialchars(number_format($u['quantity_used'], 0)) . ' ' . htmlspecialchars($u['unit_of_measurement']) . ' of ' . htmlspecialchars($u['material_name']);
                    if ($u['is_deducted']) {
                        $html .= ' <span class="deducted-status">(Deducted by ' . htmlspecialchars($u['deducted_by_username'] ?? 'N/A') . ' on ' . date('M d, Y h:i A', strtotime($u['deducted_at'])) . ')</span>';
                    }
                    $html .= '</li>';
                }
                $html .= '</ul>';
            } else {
                $html .= '<p class="text-muted text-small">No materials used reported.</p>';
            }

            // Proof image (with dynamic URL)
            if (!empty($r['proof_image'])) {
                $img_src = $base_url . htmlspecialchars($r['proof_image']);
                $html .= '<p><strong>Proof Image:</strong><br><img src="' . $img_src . '" class="report-proof-image" alt="Proof Image"></p>';
            } else {
                $html .= '<p class="text-muted text-small">No proof image uploaded.</p>';
            }
            $html .= '<hr style="margin:15px 0;">';
        }
    } else {
        $html .= '<p class="text-muted text-small">No reports yet.</p>';
    }
}

$html .= '</body></html>';

// --------------------------------------------------
// Dompdf setup
// --------------------------------------------------
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('tempDir', __DIR__ . '/tmp');

// Ensure tmp directory is writable
if (!is_dir(__DIR__ . '/tmp')) {
    mkdir(__DIR__ . '/tmp', 0777, true);
}
if (!is_writable(__DIR__ . '/tmp')) {
    chmod(__DIR__ . '/tmp', 0777);
}

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Full_System_Report_" . date('Y-m-d_His') . ".pdf", ["Attachment" => false]);

$conn->close();
exit();
