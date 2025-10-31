<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // Make sure dompdf is installed

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['id'])) {
    die("Report ID is required.");
}

$report_id = (int)$_GET['id'];

// Fetch report info
$stmt = $conn->prepare("
    SELECT r.*, p.name AS project_name, p.location 
    FROM project_reports r
    JOIN projects p ON r.project_id = p.id
    WHERE r.id = ?
");
$stmt->bind_param('i', $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    die("Invalid report ID.");
}

// Fetch materials used for this report
$materials = [];
$matStmt = $conn->prepare("
    SELECT rm.quantity_used, m.name, m.unit_of_measurement 
    FROM report_material_usage rm
    JOIN materials m ON rm.material_id = m.id
    WHERE rm.report_id = ?
");
$matStmt->bind_param('i', $report_id);
$matStmt->execute();
$materials = $matStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch proof images
$images = [];
$imgStmt = $conn->prepare("SELECT image_path FROM report_images WHERE report_id = ?");
$imgStmt->bind_param('i', $report_id);
$imgStmt->execute();
$imgRes = $imgStmt->get_result();
while ($row = $imgRes->fetch_assoc()) {
    $images[] = $row['image_path'];
}

// Initialize DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// ✅ Adjust path to your logo
$logo_path = __DIR__ . '/../assets/images/logo.png';
$logo_base64 = '';
if (file_exists($logo_path)) {
    $logo_data = file_get_contents($logo_path);
    $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
}

// ===============================
// BUILD PDF HTML
// ===============================
$html = '
<html>
<head>
<style>
    @page {
        margin: 120px 30px 50px 30px;
    }

    header {
        position: fixed;
        top: -100px;
        left: 0;
        right: 0;
        height: 80px;
        text-align: center;
        border-bottom: 2px solid #444;
        padding-bottom: 5px;
    }

    header img {
        position: absolute;
        left: 20px;
        top: 10px;
        width: 80px;
    }

    header .company-title {
        font-size: 16px;
        font-weight: bold;
        text-transform: uppercase;
        margin-top: 25px;
    }

    body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 0; }
    main { margin-top: 10px; }
    h1, h2, h3, h4 { color: #222; margin-bottom: 8px; }
    h1 { text-align: center; font-size: 20px; margin-top: 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #444; padding: 6px; }
    th { background: #f2f2f2; }
    .images { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .images img { width: 180px; height: 120px; object-fit: cover; border: 1px solid #aaa; border-radius: 4px; }
</style>
</head>
<body>

<header>
    ' . ($logo_base64 ? '<img src="' . $logo_base64 . '" alt="Logo">' : '') . '
    <div class="company-title">Sunshine Sapphire Construction and Supply Co.</div>
</header>

<main>
<h1>Daily Project Report</h1>
<h2>Project: ' . htmlspecialchars($report['project_name']) . '</h2>
<p><strong>Date:</strong> ' . htmlspecialchars(date('m-d-Y', strtotime($report['report_date']))) . '<br>
<strong>Location:</strong> ' . htmlspecialchars($report['location'] ?? 'N/A') . '<br>
<strong>Progress:</strong> ' . htmlspecialchars($report['progress_percentage']) . '%<br>
<strong>Created by:</strong> ' . htmlspecialchars($report['created_by']) . '<br>
<strong>Created at:</strong> ' . htmlspecialchars(date('m-d-Y h:i A', strtotime($report['created_at']))) . '</p>

<hr>

<h3>Work Done</h3>
<p>' . nl2br(htmlspecialchars($report['work_done'])) . '</p>

<h3>Remarks</h3>
<p>' . nl2br(htmlspecialchars($report['remarks'] ?? '—')) . '</p>
';

// ===============================
// MATERIALS TABLE
// ===============================
if (!empty($materials)) {
    $html .= '<h3>Materials Used</h3>';
    $html .= '<table><tr><th>Material</th><th>Quantity Used</th></tr>';
    foreach ($materials as $m) {
        $html .= '<tr><td>' . htmlspecialchars($m['name']) . '</td>
                  <td>' . htmlspecialchars($m['quantity_used']) . ' ' . htmlspecialchars($m['unit_of_measurement']) . '</td></tr>';
    }
    $html .= '</table>';
}

// ===============================
// IMAGES
// ===============================
if (!empty($images)) {
    $html .= '<h3>Proof Images</h3><div class="images">';
    foreach ($images as $imgFile) {
        $imgPath = __DIR__ . '/report_images/' . $imgFile;
        if (file_exists($imgPath)) {
            $imgData = file_get_contents($imgPath);
            $base64 = 'data:image/jpeg;base64,' . base64_encode($imgData);
            $html .= '<img src="' . $base64 . '" alt="Proof">';
        }
    }
    $html .= '</div>';
}

$html .= '</main></body></html>';

// Render to PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output PDF
$filename = 'Daily_Report_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $report['project_name']) . '_' . date('Ymd', strtotime($report['report_date'])) . '.pdf';
$dompdf->stream($filename, ["Attachment" => false]);
exit;
?>
