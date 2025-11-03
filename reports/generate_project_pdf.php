<?php
// reports/generate_project_pdf.php

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php'; // optional, for auth or validation

use Mpdf\Mpdf;

// ====================================================================
// 1. DATABASE QUERIES
// ====================================================================

if (!isset($_GET['project_id'])) {
    die("<h3 style='color:red;'>Invalid parameters.</h3>");
}

$project_id = (int)$_GET['project_id'];

// ✅ Fetch project info
$project = $conn->query("SELECT name, location FROM projects WHERE id = $project_id LIMIT 1")->fetch_assoc();
if (!$project) {
    die("<h3 style='color:red;'>Project not found.</h3>");
}

// ✅ Fetch all units under this project
$units_res = $conn->query("SELECT id, name, description, progress FROM project_units WHERE project_id = $project_id ORDER BY id ASC");

// ====================================================================
// 2. MPDF SETUP
// ====================================================================

$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_top' => 40,
    'margin_bottom' => 28,
    'margin_left' => 15,
    'margin_right' => 15
]);

$company_name_red = "Sunshine Sapphire";
$company_subname = "Construction and Supply, Inc.";
$address_line1 = "St. Mark Ave., Doña Juliana Subd., Brgy. Taculing";
$address_line2 = "Bacolod City, Negros Occidental";
$contact_info = "Email: sunshinesapphire19@gmail.com | Tel: 0943-130-1714";
$logo_path = __DIR__ . '/../assets/images/logo.png';

// 🎯 HEADER (same as your working version)
$headerHTML = '
<div style="text-align: center;">
    <table width="90%" style="border-collapse: collapse; border: none; margin: 0 auto;">
        <tr>
            <td width="25%" style="border: none; padding: 0 5px 0 0; vertical-align: top; text-align: right;">
                <img src="' . $logo_path . '" width="80" style="vertical-align: top; margin-top: 5px;">
            </td>
            <td width="55%" style="border: none; padding: 0; vertical-align: top; text-align: center;">
                <div style="font-size: 15pt; font-weight: bold; line-height: 1.1; margin-bottom: 2px;">
                    <span style="color: #D40000;">' . $company_name_red . '</span>
                </div>
                <div style="font-size: 15pt; font-weight: bold; line-height: 1.1; margin-bottom: 5px;">
                    <span style="color: #000;">' . $company_subname . '</span>
                </div>
                <div style="font-size: 5pt; color: #444; line-height: 1.3; text-align: left;">' . $address_line1 . '</div>
                <div style="font-size: 5pt; color: #444; line-height: 1.3; text-align: left;">' . $address_line2 . '</div>
                <div style="font-size: 5pt; color: #444; line-height: 1.3; text-align: left;">' . $contact_info . '</div>
            </td>
            <td width="20%" style="border: none;"></td>
        </tr>
    </table>
</div>
';

// 🎯 FOOTER
$footerHTML = '
<div style="width: 100%; font-size: 9pt; color: #555; border-top: 1px solid #ccc; padding-top: 5px; position: relative;">
    <div style="text-align: center; float: left;">© 2025 Sunshine Sapphire Construction and Supply, Inc. — All Rights Reserved.</div>
    <div style="text-align: right; float: right;">Page {PAGENO} of {nbpg}</div>
    <div style="clear: both;"></div>
</div>
';

$mpdf->SetHTMLHeader($headerHTML);
$mpdf->SetHTMLFooter($footerHTML);

// ====================================================================
// 3. MAIN CONTENT (Project Report for all units)
// ====================================================================

$date_generated = date("F j, Y, g:i A");

$html = '
<head>
<style>
body { font-family: DejaVu Sans, sans-serif; color: #333; font-size: 11pt; margin: 0; padding: 0; }
h2, h3, h4 { color: #004AAD; font-weight: bold; margin-top: 15px; }
h2 { font-size: 16pt; text-align: center; margin-top: 5px;}
h3 { font-size: 14pt; text-align: center; }
.section-title { font-size: 12pt; font-weight: bold; margin-top: 25px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
p, ul { font-size: 10pt; margin-bottom: 5px; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; border: 1px solid #004AAD; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; font-size: 9pt; }
th { background-color: #f2f2f2; font-weight: bold; }
.proof { max-width: 350px; height: auto; margin: 10px auto; border: 1px solid #ccc; border-radius: 4px; display: block; }
.caption { font-size: 8pt; color: #555; text-align: center; margin-bottom: 12px; }
hr { border: none; border-top: 1px dashed #aaa; margin: 15px 0; }
</style>
</head>
<body>

<h2>' . htmlspecialchars($project['name']) . ' — Full Project Report</h2>
<p style="text-align: center; font-size: 10pt; color: #666;">Location: ' . htmlspecialchars($project['location'] ?? 'N/A') . '</p>
';

// --------------------------------------------------------------------
// LOOP THROUGH EACH UNIT
// --------------------------------------------------------------------
if ($units_res && $units_res->num_rows > 0) {
    while ($unit = $units_res->fetch_assoc()) {
        $unit_id = (int)$unit['id'];

        // Fetch unit checklist, materials, and reports
        $checklist_res = $conn->query("SELECT item_description FROM project_checklists WHERE unit_id = $unit_id ORDER BY id ASC");
        $materials_res = $conn->query("SELECT name, total_quantity, unit_of_measurement FROM materials WHERE project_id = $project_id ORDER BY name ASC");
        $reports_res = $conn->query("SELECT id, report_date, progress_percentage, work_done, remarks FROM project_reports WHERE unit_id = $unit_id ORDER BY report_date DESC");

        $html .= '
        <div style="page-break-before: always;"></div>
        <h3>Unit: ' . htmlspecialchars($unit['name']) . '</h3>
        <p style="text-align: center; font-size: 9pt; color: #666;">Progress: ' . htmlspecialchars($unit['progress']) . '%</p>

        <div class="section-title">Description of the Project:</div>
        <p>' . nl2br(htmlspecialchars($unit['description'] ?? 'No description available.')) . '</p>

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

        $html .= '
        <div class="section-title">Materials:</div>
        <table>
          <tr><th width="50%">Material</th><th width="25%">Quantity</th><th width="25%">Unit of Measurement</th></tr>';
        if ($materials_res && $materials_res->num_rows > 0) {
            while ($mat = $materials_res->fetch_assoc()) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($mat['name']) . '</td>
                    <td style="text-align:center;">' . rtrim(rtrim(number_format($mat['total_quantity'], 2), '0'), '.') . '</td>
                    <td style="text-align:center;">' . htmlspecialchars($mat['unit_of_measurement']) . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="3" style="text-align:center;"><em>No materials recorded.</em></td></tr>';
        }
        $html .= '</table>';

        $html .= '
        <div class="section-title">Project Reports:</div>';
        if ($reports_res && $reports_res->num_rows > 0) {
            while ($r = $reports_res->fetch_assoc()) {
                $html .= '<div style="margin-top: 15px;">
                    <p><strong>Date:</strong> ' . ($r['report_date'] ? date('F d, Y', strtotime($r['report_date'])) : 'N/A') . '</p>
                    <p><strong>Progress:</strong> ' . htmlspecialchars($r['progress_percentage']) . '%</p>';
                if (!empty($r['work_done'])) {
                    $html .= '<p><strong>Work Done:</strong><br>' . nl2br(htmlspecialchars($r['work_done'])) . '</p>';
                }
                if (!empty($r['remarks'])) {
                    $html .= '<p><strong>Remarks:</strong><br>' . nl2br(htmlspecialchars($r['remarks'])) . '</p>';
                }

                $rid = (int)$r['id'];
                $images_res = $conn->query("SELECT image_path FROM report_images WHERE report_id = $rid");
                if ($images_res && $images_res->num_rows > 0) {
                    while ($img = $images_res->fetch_assoc()) {
                        $img_path = __DIR__ . '/report_images/' . $img['image_path'];
                        if (file_exists($img_path)) {
                            $html .= '<img class="proof" src="' . $img_path . '" alt="Proof Image">';
                        } else {
                            $html .= '<div class="caption" style="color:#b00;">Missing image: ' . htmlspecialchars($img['image_path']) . '</div>';
                        }
                    }
                }
                $html .= '<hr></div>';
            }
        } else {
            $html .= '<p><em>No project reports found for this unit.</em></p>';
        }
    }
} else {
    $html .= '<p><em>No units found for this project.</em></p>';
}

$html .= '
<p style="text-align:right; margin-top:25px; font-size:10pt; color:#555;">
Generated on ' . $date_generated . '
</p>

</body>
</html>
';

// ====================================================================
// 4. OUTPUT PDF
// ====================================================================

$mpdf->WriteHTML($html);
$mpdf->Output('SunshineSapphire_Project_Report_' . $project_id . '_' . date("Ymd_His") . '.pdf', 'I');
?>
