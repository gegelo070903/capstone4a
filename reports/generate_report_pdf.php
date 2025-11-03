<?php
// reports/generate_report_pdf.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php'; // Assuming this contains validation/login logic

use Mpdf\Mpdf;

// ====================================================================
// 1. ADD DATABASE QUERIES (Dynamic Content Fetch)
// ====================================================================

// ✅ Validate parameters
if (!isset($_GET['unit_id']) || !isset($_GET['project_id'])) {
    die("<h3 style='color:red;'>Invalid parameters.</h3>");
}

$unit_id = (int)$_GET['unit_id'];
$project_id = (int)$_GET['project_id'];

// ✅ Fetch project info
$project = $conn->query("SELECT name, location FROM projects WHERE id = $project_id LIMIT 1")->fetch_assoc();

// ✅ Fetch unit info
$unit = $conn->query("SELECT name, description, progress FROM project_units WHERE id = $unit_id LIMIT 1")->fetch_assoc();

// ✅ Fetch checklist (no status)
$checklist_res = $conn->query("SELECT item_description FROM project_checklists WHERE unit_id = $unit_id ORDER BY id ASC");

// ✅ Fetch materials
$materials_res = $conn->query("SELECT name, total_quantity, unit_of_measurement FROM materials WHERE project_id = $project_id ORDER BY name ASC");

// ✅ Fetch reports
$reports_res = $conn->query("SELECT id, report_date, progress_percentage, work_done, remarks, created_by FROM project_reports WHERE unit_id = $unit_id ORDER BY report_date DESC");

// Check for minimum data
if (!$project || !$unit) {
    die("<h3 style='color:red;'>Project or Unit not found.</h3>");
}

// ====================================================================
// 2. MPDF SETUP
// ====================================================================

// Initialize mPDF
$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_top' => 40,
    'margin_bottom' => 28,
    'margin_left' => 15,
    'margin_right' => 15
]);

// Variables for Header
$company_name_red = "Sunshine Sapphire";
$company_subname = "Construction and Supply, Inc.";
$address_line1 = "St. Mark Ave., Doña Juliana Subd., Brgy. Taculing";
$address_line2 = "Bacolod City, Negros Occidental";
$contact_info = "Email: sunshinesapphire19@gmail.com | Tel: 0943-130-1714";
$logo_path = __DIR__ . '/../assets/images/logo.png';

// 🎯 HEADER: Finalized Centered Block Structure from your last message (UNCHANGED)
$headerHTML = '
<div style="text-align: center;">
    
    <!-- Inner Table for Logo and All Text Content (Increased width to 90% for more left room) -->
    <table width="90%" style="border-collapse: collapse; border: none; margin: 0 auto;">
        <tr>
            <!-- Logo Cell: Increased to 25% width, padding right is 5px -->
            <td width="25%" style="border: none; padding: 0 5px 0 0; vertical-align: top; text-align: right;">
                <img src="' . $logo_path . '" width="80" style="vertical-align: top; margin-top: 5px;">
            </td>
            
            <!-- Text Content Cell: Set to 55% width, alignment changed to LEFT -->
            <td width="55%" style="border: none; padding: 0; vertical-align: top; text-align: center;">
                
                <!-- Company Name (15pt) -->
                <div style="font-size: 15pt; font-weight: bold; line-height: 1.1; margin-bottom: 2px;">
                    <span style="color: #D40000;">' . $company_name_red . '</span>
                </div>
                <div style="font-size: 15pt; font-weight: bold; line-height: 1.1; margin-bottom: 5px;">
                    <span style="color: #000;">' . $company_subname . '</span>
                </div>
                
                <!-- Address and Contact (5pt) - Aligned LEFT to match cell alignment -->
                <div style="font-size: 5pt; color: #444; line-height: 1.3; text-align: left;">
                    ' . $address_line1 . '
                </div>
                <div style="font-size: 5pt; color: #444; line-height: 1.3; text-align: left;">
                    ' . $address_line2 . '
                </div>
                <div style="font-size: 5pt; color: #444; line-height: 1.3; text-align: left;">
                    ' . $contact_info . '
                </div>
            </td>
            <!-- Empty Cell: 20% (100% - 25% - 55%) to keep the overall table centered relative to the page -->
            <td width="20%" style="border: none;"></td> 
        </tr>
    </table>
</div>
';

// FOOTER (MODIFIED: Aligned company copyright to LEFT, and kept page numbers on the RIGHT)
$footerHTML = '
<div style="width: 100%; font-size: 9pt; color: #555; border-top: 1px solid #ccc; padding-top: 5px; position: relative;">
    <div style="text-align: center; float: left;">© 2025 Sunshine Sapphire Construction and Supply, Inc. — All Rights Reserved.</div>
    <div style="text-align: right; float: right;">Page {PAGENO} of {nbpg}</div>
    <div style="clear: both;"></div>
</div>
';

// Apply header and footer
$mpdf->SetHTMLHeader($headerHTML);
$mpdf->SetHTMLFooter($footerHTML);

// ====================================================================
// 3. MAIN CONTENT GENERATION (MODIFIED for extra spacing and no decimals)
// ====================================================================

$date_generated = date("F j, Y, g:i A");

$html = '
<head>
<style>
body { font-family: DejaVu Sans, sans-serif; color: #333; font-size: 11pt; margin: 0; padding: 0; }
h2, h3, h4 { color: #004AAD; font-weight: bold; margin-top: 15px; }
h2 { font-size: 16pt; text-align: center; margin-top: 5px;}
h3 { font-size: 14pt; text-align: center; }
/* INCREASE MARGIN-TOP to create space after preceding content */
.section-title { font-size: 12pt; font-weight: bold; margin-top: 25px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
p, ul { font-size: 10pt; margin-bottom: 5px; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; border: 1px solid #004AAD; }
/* Added .materials-table to target the project materials table, keep general table styling for report materials */
.materials-table th, .materials-table td { border: 1px solid #004AAD; padding: 8px; text-align: left; vertical-align: top; font-size: 9pt; }
.materials-table th { background-color: #f2f2f2; font-weight: bold; }
/* Styling for materials used in reports */
.report-material-table { 
    width: 100%; 
    border-collapse: collapse; 
    margin: 5px 0 10px 0; 
}
.report-material-table th, .report-material-table td { 
    border: 1px solid #ccc; 
    padding: 6px; 
    font-size: 9pt; 
}
.report-material-table th {
    background-color: #f2f2f2;
    font-weight: bold;
}

.proof { max-width: 150px; height: auto; margin: 5px; border: 1px solid #ccc; border-radius: 4px; display: block; }
.caption { font-size: 8pt; color: #555; margin-left: 5px; margin-bottom: 10px; }
</style>
</head>
<body>

<!-- Project Title and Unit -->
<h2 style="margin-top:0;">' . htmlspecialchars($project['name']) . ' Report</h2>
<h3>Unit: ' . htmlspecialchars($unit['name']) . '</h3>
<p style="text-align: center; font-size: 9pt; color: #666; margin-top:-10px;">Overall Progress: ' . htmlspecialchars($unit['progress']) . '%</p>


<!-- Description -->
<div class="section-title">Description of the Project:</div>
<p>' . nl2br(htmlspecialchars($unit['description'] ?? 'No description available.')) . '</p>
<!-- Extra space after description flow -->


<!-- Checklist -->
<div class="section-title">Checklist of the Project:</div>';

if ($checklist_res && $checklist_res->num_rows > 0) {
    $html .= '<ul>';
    while ($row = $checklist_res->fetch_assoc()) {
        $html .= '<li>' . htmlspecialchars($row['item_description']) . '</li>';
    }
    $html .= '</ul>';
} else {
    $html .= '<p><em>No checklist items found.</em></p>';
}
// Extra space after checklist flow


$html .= '
<!-- Materials Table -->
<div class="section-title">Materials:</div>
<table class="materials-table">
  <tr>
    <th width="50%">Material</th>
    <th width="25%">Quantity</th>
    <th width="25%">Unit of Measurement</th>
  </tr>';

if ($materials_res && $materials_res->num_rows > 0) {
    while ($mat = $materials_res->fetch_assoc()) {
        $html .= '
          <tr>
            <td>' . htmlspecialchars($mat['name']) . '</td>
            <!-- MODIFIED: Removed decimals on quantity -->
            <td style="text-align:center;">' . intval($mat['total_quantity']) . '</td>
            <td style="text-align:center;">' . htmlspecialchars($mat['unit_of_measurement']) . '</td>
          </tr>';
    }
} else {
    $html .= '<tr><td colspan="3" style="text-align:center;"><em>No materials recorded.</em></td></tr>';
}
$html .= '</table>';
// Extra space after materials flow


$html .= '
<!-- Reports and Proof Images -->
<div class="section-title">Project Reports:</div>';

if ($reports_res && $reports_res->num_rows > 0) {
    while ($r = $reports_res->fetch_assoc()) {
        $rid = (int)$r['id'];

        $html .= '<div style="margin-top: 15px;">';
        $html .= '<p><strong>Date:</strong> ' . date('F d, Y', strtotime($r['report_date'])) . '</p>';
        $html .= '<p><strong>Progress:</strong> ' . htmlspecialchars($r['progress_percentage']) . '%</p>';
        
        if (!empty($r['work_done'])) {
            $html .= '<p><strong>Work Done:</strong><br>' . nl2br(htmlspecialchars($r['work_done'])) . '</p>';
        }
        if (!empty($r['remarks'])) {
            $html .= '<p><strong>Remarks:</strong><br>' . nl2br(htmlspecialchars($r['remarks'])) . '</p>';
        }

        // ✅ NEW: Display materials used for this report (using mPDF HTML)
        $materials_used = get_materials_used_for_report($conn, $rid);
        if (!empty($materials_used)) {
            $html .= '<p style="font-weight: bold; font-size: 10pt; margin-top: 10px;">Materials Used:</p>';
            $html .= '<table class="report-material-table">
                        <thead>
                            <tr>
                                <th width="60%">Material</th>
                                <th width="20%" style="text-align:center;">Quantity</th>
                                <th width="20%" style="text-align:center;">Unit</th>
                            </tr>
                        </thead>
                        <tbody>';
            foreach ($materials_used as $m) {
                // Ensure supplier is not shown in this specific table structure
                $html .= '<tr>
                            <td>' . htmlspecialchars($m['material_name']) . '</td>
                            <td style="text-align:center;">' . intval($m['quantity_used']) . '</td>
                            <td style="text-align:center;">' . htmlspecialchars($m['unit']) . '</td>
                          </tr>';
            }
            $html .= '  </tbody>
                     </table>';
        }

        // Fetch attached images for this report
        $images_res = $conn->query("SELECT image_path FROM report_images WHERE report_id = $rid");
        
        if ($images_res && $images_res->num_rows > 0) {
            $html .= '<div style="margin-top: 10px;">';
            while ($img = $images_res->fetch_assoc()) {
                // ✅ Corrected image path to be absolute for mPDF's local file access
                $img_path = __DIR__ . '/report_images/' . $img['image_path'];
                
                if (file_exists($img_path)) {
                    // Sticking with the local path for simplicity:
                    $html .= '<img class="proof" src="' . $img_path . '" alt="Proof Image" style="float:left;">';
                } else {
                    $html .= '<div class="caption" style="color:#b00;">Missing image: ' . htmlspecialchars($img['image_path']) . '</div>';
                }
            }
            $html .= '<div style="clear: both;"></div></div>'; // Clear float
        }
        $html .= '<hr style="border: none; border-top: 1px dashed #ccc; margin: 15px 0;">';
        $html .= '</div>';
    }
} else {
    $html .= '<p><em>No project reports found for this unit.</em></p>';
}

$html .= '
<!-- Small Date Footer at the Bottom -->
<p style="text-align:right; margin-top:25px; font-size:10pt; color:#555;">
Generated on ' . date("F j, Y, g:i A") . '
</p>

</body>
</html>
';

// Render PDF
$mpdf->WriteHTML($html);
$mpdf->Output('SunshineSapphire_Unit_Report_' . $unit_id . '_' . date("Ymd_His") . '.pdf', 'I');
?>