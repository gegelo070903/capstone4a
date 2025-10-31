<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // Ensure DOMPDF is installed

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['project_id'])) {
    die("Project ID is required.");
}

$project_id = (int)$_GET['project_id'];

// Fetch project info
$ps = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$ps->bind_param('i', $project_id);
$ps->execute();
$project = $ps->get_result()->fetch_assoc();

if (!$project) {
    die("Invalid project ID.");
}

// Fetch all reports for this project
$reports = [];
$stmt = $conn->prepare("
    SELECT * FROM project_reports 
    WHERE project_id = ? 
    ORDER BY report_date ASC
");
$stmt->bind_param('i', $project_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $reports[] = $r;

if (!$reports) {
    die("No reports found for this project.");
}

// Initialize DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// ✅ Adjust path to your logo (must exist)
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
        margin: 120px 30px 50px 30px; /* top, right, bottom, left */
    }

    /* Fixed header on every page */
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
    .section { margin-top: 30px; page-break-inside: avoid; }
    .images { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .images img { width: 180px; height: 120px; object-fit: cover; border: 1px solid #aaa; border-radius: 4px; }
    .page-break { page-break-after: always; }
</style>
</head>
<body>

<header>
    ' . ($logo_base64 ? '<img src="' . $logo_base64 . '" alt="Logo">' : '') . '
    <div class="company-title">Sunshine Sapphire Construction and Supply Co.</div>
</header>

<main>
<h1>Project Report Summary</h1>
<h2>Project: ' . htmlspecialchars($project['name']) . '</h2>
<p><strong>Location:</strong> ' . htmlspecialchars($project['location'] ?? 'N/A') . '<br>
<strong>Total Reports:</strong> ' . count($reports) . '<br>
<strong>Generated on:</strong> ' . date('m-d-Y h:i A') . '</p>
<hr>
';

// Loop through reports
foreach ($reports as $index => $report) {
    $html .= '<div class="section">';
    $html .= '<h3>Report Date: ' . htmlspecialchars(date('m-d-Y', strtotime($report['report_date']))) . '</h3>';
    $html .= '<p><strong>Progress:</strong> ' . htmlspecialchars($report['progress_percentage']) . '%</p>';
    $html .= '<p><strong>Work Done:</strong><br>' . nl2br(htmlspecialchars($report['work_done'])) . '</p>';
    $html .= '<p><strong>Remarks:</strong><br>' . nl2br(htmlspecialchars($report['remarks'] ?? '—')) . '</p>';
    $html .= '<p><strong>Created by:</strong> ' . htmlspecialchars($report['created_by']) . '</p>';
    $html .= '<p><strong>Created at:</strong> ' . htmlspecialchars(date('m-d-Y h:i A', strtotime($report['created_at']))) . '</p>';

    // Fetch materials used
    $matStmt = $conn->prepare("
        SELECT rm.quantity_used, m.name, m.unit_of_measurement 
        FROM report_material_usage rm
        JOIN materials m ON rm.material_id = m.id
        WHERE rm.report_id = ?
    ");
    $matStmt->bind_param('i', $report['id']);
    $matStmt->execute();
    $matRes = $matStmt->get_result();

    if ($matRes->num_rows > 0) {
        $html .= '<h4>Materials Used</h4>';
        $html .= '<table><tr><th>Material</th><th>Quantity Used</th></tr>';
        while ($m = $matRes->fetch_assoc()) {
            $html .= '<tr><td>' . htmlspecialchars($m['name']) . '</td>
                      <td>' . htmlspecialchars($m['quantity_used']) . ' ' . htmlspecialchars($m['unit_of_measurement']) . '</td></tr>';
        }
        $html .= '</table>';
    }

    // Fetch proof images
    $imgStmt = $conn->prepare("SELECT image_path FROM report_images WHERE report_id = ?");
    $imgStmt->bind_param('i', $report['id']);
    $imgStmt->execute();
    $imgRes = $imgStmt->get_result();

    if ($imgRes->num_rows > 0) {
        $html .= '<h4>Proof Images</h4><div class="images">';
        while ($img = $imgRes->fetch_assoc()) {
            $imgPath = __DIR__ . '/report_images/' . $img['image_path'];
            if (file_exists($imgPath)) {
                $imgData = file_get_contents($imgPath);
                $base64 = 'data:image/jpeg;base64,' . base64_encode($imgData);
                $html .= '<img src="' . $base64 . '" alt="Proof">';
            }
        }
        $html .= '</div>';
    }

    if ($index < count($reports) - 1) {
        $html .= '<div class="page-break"></div>';
    }

    $html .= '</div>';
}

$html .= '</main></body></html>';

// Render to PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output PDF
$filename = 'Project_Report_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['name']) . '_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ["Attachment" => false]);
exit;
?>
