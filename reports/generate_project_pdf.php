<?php
// reports/generate_project_pdf.php

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

use Mpdf\Mpdf;

// ====================================================================
// 1. DATABASE QUERIES
// ====================================================================

if (!isset($_GET['project_id'])) {
    die("<h3 style='color:red;'>Invalid parameters.</h3>");
}

$project_id = (int)$_GET['project_id'];

// Fetch project info with calculated progress
$project = $conn->query("
    SELECT p.*, 
           (SELECT COUNT(*) FROM project_units pu WHERE pu.project_id = p.id) as total_units,
           COALESCE((SELECT ROUND(AVG(pu.progress)) FROM project_units pu WHERE pu.project_id = p.id), 0) as overall_progress
    FROM projects p 
    WHERE p.id = $project_id 
    LIMIT 1
")->fetch_assoc();

if (!$project) {
    die("<h3 style='color:red;'>Project not found.</h3>");
}

// Fetch all units under this project
$units_res = $conn->query("SELECT id, name, description, progress FROM project_units WHERE project_id = $project_id ORDER BY id ASC");

// ====================================================================
// 2. MPDF SETUP
// ====================================================================

$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_top' => 45,
    'margin_bottom' => 25,
    'margin_left' => 18,
    'margin_right' => 18
]);

$company_name_red = "Sunshine Sapphire";
$company_subname = "Construction and Supply, Inc.";
$address_line1 = "St. Mark Ave., Doña Juliana Subd., Brgy. Taculing";
$address_line2 = "Bacolod City, Negros Occidental";
$contact_info = "Email: sunshinesapphire19@gmail.com | Tel: 0943-130-1714";
$logo_path = __DIR__ . '/../assets/images/logo.png';

// Header
$headerHTML = '
<table width="100%" style="border-bottom: 2px solid #004AAD; padding-bottom: 8px;">
    <tr>
        <td width="15%" style="vertical-align: middle;">
            <img src="' . $logo_path . '" width="70">
        </td>
        <td width="85%" style="vertical-align: middle;">
            <div style="font-size: 16pt; font-weight: bold; color: #D40000; margin-bottom: 2px;">' . $company_name_red . '</div>
            <div style="font-size: 12pt; font-weight: bold; color: #333;">' . $company_subname . '</div>
            <div style="font-size: 8pt; color: #666; margin-top: 3px;">' . $address_line1 . ' | ' . $address_line2 . '</div>
            <div style="font-size: 8pt; color: #666;">' . $contact_info . '</div>
        </td>
    </tr>
</table>
';

// Footer
$footerHTML = '
<table width="100%" style="border-top: 1px solid #ccc; padding-top: 5px; font-size: 8pt; color: #666;">
    <tr>
        <td width="50%">© 2025 Sunshine Sapphire Construction and Supply, Inc.</td>
        <td width="50%" style="text-align: right;">Page {PAGENO} of {nbpg}</td>
    </tr>
</table>
';

$mpdf->SetHTMLHeader($headerHTML);
$mpdf->SetHTMLFooter($footerHTML);

// ====================================================================
// 3. STYLES
// ====================================================================

$date_generated = date("F j, Y \\a\\t g:i A");

$styles = '
<style>
    body { 
        font-family: DejaVu Sans, Arial, sans-serif; 
        color: #333; 
        font-size: 10pt; 
        line-height: 1.5;
    }
    
    .report-title {
        text-align: center;
        margin: 20px 0 10px 0;
    }
    .report-title h1 {
        font-size: 20pt;
        color: #004AAD;
        margin: 0 0 5px 0;
        font-weight: bold;
    }
    .report-title .subtitle {
        font-size: 11pt;
        color: #666;
    }
    
    .project-summary {
        background: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px 20px;
        margin: 20px 0;
    }
    .project-summary table {
        width: 100%;
        border: none;
    }
    .project-summary td {
        padding: 5px 10px;
        border: none;
        font-size: 10pt;
    }
    .project-summary .label {
        font-weight: bold;
        color: #555;
        width: 30%;
    }
    .project-summary .value {
        color: #333;
    }
    
    .section-header {
        background: #004AAD;
        color: white;
        padding: 8px 15px;
        font-size: 12pt;
        font-weight: bold;
        margin: 25px 0 15px 0;
        border-radius: 4px;
    }
    
    .subsection-header {
        background: #f0f4f8;
        color: #004AAD;
        padding: 6px 12px;
        font-size: 10pt;
        font-weight: bold;
        margin: 15px 0 10px 0;
        border-left: 4px solid #004AAD;
    }
    
    .unit-card {
        border: 1px solid #ddd;
        border-radius: 8px;
        margin: 15px 0;
        overflow: hidden;
    }
    .unit-header {
        background: #004AAD;
        color: white;
        padding: 12px 15px;
    }
    .unit-header h2 {
        margin: 0;
        font-size: 14pt;
    }
    .unit-header .progress-badge {
        background: rgba(255,255,255,0.2);
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 9pt;
        margin-left: 10px;
    }
    .unit-body {
        padding: 15px;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin: 10px 0;
    }
    .data-table th {
        background: #004AAD;
        color: white;
        padding: 10px 12px;
        text-align: left;
        font-size: 9pt;
        font-weight: bold;
    }
    .data-table td {
        padding: 8px 12px;
        border-bottom: 1px solid #eee;
        font-size: 9pt;
    }
    .data-table tr:nth-child(even) {
        background: #f9f9f9;
    }
    
    .checklist-table td:first-child {
        width: 30px;
        text-align: center;
    }
    .check-icon {
        color: #22c55e;
        font-weight: bold;
    }
    .pending-icon {
        color: #f59e0b;
    }
    
    .report-entry {
        background: #fafafa;
        border: 1px solid #e5e5e5;
        border-radius: 6px;
        padding: 12px 15px;
        margin: 10px 0;
    }
    .report-entry .report-date {
        font-weight: bold;
        color: #004AAD;
        font-size: 10pt;
        margin-bottom: 8px;
        border-bottom: 1px solid #eee;
        padding-bottom: 5px;
    }
    .report-entry .report-field {
        margin: 5px 0;
    }
    .report-entry .report-label {
        font-weight: bold;
        color: #555;
        display: inline;
    }
    
    .image-gallery {
        margin: 15px 0;
        text-align: center;
    }
    .proof-image {
        max-width: 280px;
        max-height: 200px;
        border: 1px solid #ddd;
        border-radius: 6px;
        margin: 8px;
    }
    .image-caption {
        font-size: 8pt;
        color: #666;
        text-align: center;
        margin-top: 5px;
        font-style: italic;
    }
    
    .no-data {
        color: #999;
        font-style: italic;
        text-align: center;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 4px;
    }
    
    .generated-info {
        text-align: right;
        font-size: 8pt;
        color: #888;
        margin-top: 30px;
        padding-top: 10px;
        border-top: 1px dashed #ccc;
    }
    
    .page-break {
        page-break-before: always;
    }
    
    .toc-item {
        padding: 5px 0;
        border-bottom: 1px dotted #ccc;
    }
</style>
';

// ====================================================================
// 4. BUILD HTML CONTENT
// ====================================================================

$html = $styles . '<body>';

// --- Cover / Title Page ---
$html .= '
<div class="report-title">
    <h1>PROJECT DOCUMENTATION REPORT</h1>
    <div class="subtitle">' . htmlspecialchars($project['name']) . '</div>
</div>

<div class="project-summary">
    <table>
        <tr>
            <td class="label">Project Name:</td>
            <td class="value">' . htmlspecialchars($project['name']) . '</td>
        </tr>
        <tr>
            <td class="label">Location:</td>
            <td class="value">' . htmlspecialchars($project['location'] ?? 'N/A') . '</td>
        </tr>
        <tr>
            <td class="label">Status:</td>
            <td class="value">' . htmlspecialchars($project['status'] ?? 'N/A') . '</td>
        </tr>
        <tr>
            <td class="label">Total Units:</td>
            <td class="value">' . (int)$project['total_units'] . '</td>
        </tr>
        <tr>
            <td class="label">Overall Progress:</td>
            <td class="value">' . (int)$project['overall_progress'] . '%</td>
        </tr>
        <tr>
            <td class="label">Date Created:</td>
            <td class="value">' . ($project['created_at'] ? date('F d, Y', strtotime($project['created_at'])) : 'N/A') . '</td>
        </tr>
    </table>
</div>
';

// --- Table of Contents ---
$html .= '<div class="section-header">TABLE OF CONTENTS</div>';
$html .= '<div style="padding: 10px 0;">';

// Reset units result pointer
$units_res->data_seek(0);
$unit_num = 1;
while ($toc_unit = $units_res->fetch_assoc()) {
    $html .= '<div class="toc-item">' . $unit_num . '. ' . htmlspecialchars($toc_unit['name']) . ' <span style="float:right; color:#666;">' . (int)$toc_unit['progress'] . '% Complete</span></div>';
    $unit_num++;
}
$html .= '</div>';

// --- Materials Summary (Project Level) ---
$materials_res = $conn->query("SELECT name, total_quantity, remaining_quantity, unit_of_measurement FROM materials WHERE project_id = $project_id ORDER BY name ASC");

$html .= '<div class="section-header">PROJECT MATERIALS SUMMARY</div>';
if ($materials_res && $materials_res->num_rows > 0) {
    $html .= '
    <table class="data-table">
        <tr>
            <th width="40%">Material Name</th>
            <th width="20%">Total Qty</th>
            <th width="20%">Remaining</th>
            <th width="20%">Unit</th>
        </tr>';
    while ($mat = $materials_res->fetch_assoc()) {
        $html .= '<tr>
            <td>' . htmlspecialchars($mat['name']) . '</td>
            <td style="text-align:center;">' . number_format($mat['total_quantity'], 0) . '</td>
            <td style="text-align:center;">' . number_format($mat['remaining_quantity'], 0) . '</td>
            <td style="text-align:center;">' . htmlspecialchars($mat['unit_of_measurement']) . '</td>
        </tr>';
    }
    $html .= '</table>';
} else {
    $html .= '<div class="no-data">No materials recorded for this project.</div>';
}

// --- Loop Through Each Unit ---
$units_res->data_seek(0);
while ($unit = $units_res->fetch_assoc()) {
    $unit_id = (int)$unit['id'];
    
    $html .= '<div class="page-break"></div>';
    
    // Unit Header Card
    $html .= '
    <div class="unit-card">
        <div class="unit-header">
            <h2>' . htmlspecialchars($unit['name']) . ' <span class="progress-badge">' . (int)$unit['progress'] . '% Complete</span></h2>
        </div>
        <div class="unit-body">
            <p><strong>Description:</strong> ' . nl2br(htmlspecialchars($unit['description'] ?? 'No description provided.')) . '</p>
        </div>
    </div>';
    
    // --- Unit Checklist ---
    $checklist_res = $conn->query("SELECT item_description, is_completed FROM project_checklists WHERE unit_id = $unit_id ORDER BY id ASC");
    
    $html .= '<div class="subsection-header">Checklist Items</div>';
    if ($checklist_res && $checklist_res->num_rows > 0) {
        $html .= '
        <table class="data-table checklist-table">
            <tr>
                <th width="10%">Status</th>
                <th width="90%">Task Description</th>
            </tr>';
        while ($item = $checklist_res->fetch_assoc()) {
            $status_text = $item['is_completed'] ? 'DONE' : 'PENDING';
            $status_color = $item['is_completed'] ? '#22c55e' : '#f59e0b';
            $html .= '<tr>
                <td style="text-align:center; color:' . $status_color . '; font-weight:bold;">' . $status_text . '</td>
                <td>' . htmlspecialchars($item['item_description']) . '</td>
            </tr>';
        }
        $html .= '</table>';
    } else {
        $html .= '<div class="no-data">No checklist items found.</div>';
    }
    
    // --- Unit Reports ---
    $reports_res = $conn->query("SELECT id, report_date, progress_percentage, work_done, remarks FROM project_reports WHERE unit_id = $unit_id ORDER BY report_date DESC");
    
    $html .= '<div class="subsection-header">Progress Reports</div>';
    if ($reports_res && $reports_res->num_rows > 0) {
        while ($r = $reports_res->fetch_assoc()) {
            $html .= '
            <div class="report-entry">
                <div class="report-date">Report Date: ' . ($r['report_date'] ? date('F d, Y', strtotime($r['report_date'])) : 'N/A') . '</div>
                <div class="report-field"><span class="report-label">Progress:</span> ' . (int)$r['progress_percentage'] . '%</div>';
            
            if (!empty($r['work_done'])) {
                $html .= '<div class="report-field"><span class="report-label">Work Done:</span> ' . nl2br(htmlspecialchars($r['work_done'])) . '</div>';
            }
            if (!empty($r['remarks'])) {
                $html .= '<div class="report-field"><span class="report-label">Remarks:</span> ' . nl2br(htmlspecialchars($r['remarks'])) . '</div>';
            }
            
            // Report Images
            $rid = (int)$r['id'];
            $images_res = $conn->query("SELECT image_path FROM report_images WHERE report_id = $rid");
            if ($images_res && $images_res->num_rows > 0) {
                $html .= '<div style="margin-top: 10px;"><strong>Proof Images:</strong></div>';
                $html .= '<div class="image-gallery">';
                while ($img = $images_res->fetch_assoc()) {
                    $img_path = __DIR__ . '/report_images/' . $img['image_path'];
                    if (file_exists($img_path)) {
                        $html .= '<img class="proof-image" src="' . $img_path . '">';
                    }
                }
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
    } else {
        $html .= '<div class="no-data">No progress reports found for this unit.</div>';
    }
    
    // --- Checklist Proof Images ---
    $checklist_images_res = $conn->query("
        SELECT pc.item_description, ci.image_path 
        FROM project_checklists pc
        JOIN checklist_images ci ON ci.checklist_id = pc.id
        WHERE pc.unit_id = $unit_id
        ORDER BY pc.id, ci.id
    ");
    
    if ($checklist_images_res && $checklist_images_res->num_rows > 0) {
        $html .= '<div class="subsection-header">Checklist Documentation Photos</div>';
        $current_item = '';
        
        while ($cimg = $checklist_images_res->fetch_assoc()) {
            if ($current_item !== $cimg['item_description']) {
                if ($current_item !== '') {
                    $html .= '</div>'; // Close previous gallery
                }
                $current_item = $cimg['item_description'];
                $html .= '<p style="margin: 15px 0 5px 0; font-weight: bold; color: #004AAD;">' . htmlspecialchars($current_item) . '</p>';
                $html .= '<div class="image-gallery">';
            }
            
            $cimg_path = __DIR__ . '/../uploads/checklist_proofs/' . $cimg['image_path'];
            if (file_exists($cimg_path)) {
                $html .= '<img class="proof-image" src="' . $cimg_path . '">';
            }
        }
        $html .= '</div>'; // Close last gallery
    }
}

$html .= '</body>';

// ====================================================================
// 5. OUTPUT PDF
// ====================================================================

// Log the PDF generation activity
log_activity($conn, 'GENERATE_PDF', "Generated PDF report for Project: {$project['name']} (ID: $project_id)");

$mpdf->WriteHTML($html);
$mpdf->Output('SunshineSapphire_' . preg_replace('/[^a-zA-Z0-9]/', '_', $project['name']) . '_Report_' . date("Ymd") . '.pdf', 'I');
?>
