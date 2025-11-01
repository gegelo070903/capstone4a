<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Dompdf autoload

use Dompdf\Dompdf;
use Dompdf\Options;

// ✅ Validate parameters
if (!isset($_GET['unit_id']) || !isset($_GET['project_id'])) {
    die("<h3 style='color:red;'>Invalid parameters.</h3>");
}

$unit_id = (int)$_GET['unit_id'];
$project_id = (int)$_GET['project_id'];

// ✅ Fetch project and unit details
$project = $conn->query("SELECT name, location FROM projects WHERE id = $project_id LIMIT 1")->fetch_assoc();
$unit = $conn->query("SELECT name, progress FROM project_units WHERE id = $unit_id LIMIT 1")->fetch_assoc();

if (!$project || !$unit) {
    die("<h3 style='color:red;'>Project or unit not found.</h3>");
}

// ✅ Fetch reports for this unit
$reports = $conn->query("
    SELECT report_date, progress_percentage, work_done, remarks, created_by, created_at
    FROM project_reports
    WHERE unit_id = $unit_id
    ORDER BY report_date DESC
");

// ✅ Setup Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->setChroot(__DIR__ . '/../');

$dompdf = new Dompdf($options);

// ✅ Convert logo image to Base64 (guaranteed to show)
$logoPath = realpath(__DIR__ . '/../assets/images/Sunshine_Sapphire_Construction_and_Supply_Logo.png');
$logoBase64 = '';

if ($logoPath && file_exists($logoPath)) {
    $logoData = file_get_contents($logoPath);
    $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
}

// ✅ Start buffering
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($unit['name']) ?> - Report</title>
<style>
    @page {
        margin: 140px 50px 60px 50px;
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        color: #111827;
        font-size: 12pt;
    }

    /* === HEADER === */
    header {
        position: fixed;
        top: -120px;
        left: 0;
        right: 0;
        height: 100px;
        border-bottom: 2px solid #1e3a8a;
        display: flex;
        align-items: center;
        padding: 0 30px;
    }

    header img {
        width: 75px;
        height: auto;
        margin-right: 15px;
    }

    .company-info h1 {
        font-size: 18pt;
        color: #1e3a8a;
        margin: 0;
    }

    .company-info p {
        margin: 2px 0;
        font-size: 10pt;
        color: #374151;
    }

    /* === FOOTER === */
    footer {
        position: fixed;
        bottom: -40px;
        left: 0;
        right: 0;
        height: 30px;
        border-top: 1px solid #ddd;
        text-align: right;
        font-size: 10pt;
        color: #6b7280;
        padding-right: 10px;
    }

    /* === CONTENT === */
    .unit-summary {
        text-align: center;
        margin-bottom: 25px;
    }

    .unit-summary h2 {
        font-size: 16pt;
        color: #1e3a8a;
        margin-bottom: 8px;
    }

    .unit-summary h3 {
        margin: 0;
        font-size: 13pt;
    }

    .unit-summary p {
        margin: 3px 0;
    }

    hr {
        border: none;
        border-top: 1px solid #1e3a8a;
        margin: 15px 0;
    }

    .report {
        border: 1px solid #ddd;
        border-radius: 8px;
        background-color: #f9fafb;
        padding: 15px 20px;
        margin-bottom: 20px;
    }

    .report-date {
        color: #1e3a8a;
        font-weight: bold;
    }

    .meta {
        font-size: 11pt;
        color: #6b7280;
        margin-top: 4px;
    }

    .pagenum:before {
        content: counter(page);
    }
</style>
</head>
<body>
<header>
    <?php if ($logoBase64): ?>
        <img src="<?= $logoBase64 ?>" alt="Company Logo">
    <?php endif; ?>
    <div class="company-info">
        <h1>Sunshine Sapphire Construction and Supply</h1>
        <p>Brgy. Estefania, Bacolod City, Negros Occidental</p>
        <p>Email: sunshinebuilds@gmail.com | Tel: (034) 123-4567</p>
    </div>
</header>

<footer>
    Page <span class="pagenum"></span> | Generated on <?= date('F d, Y h:i A') ?>
</footer>

<main>
    <div class="unit-summary">
        <h2>Unit Report</h2>
        <h3><?= htmlspecialchars($project['name']) ?></h3>
        <p>Location: <?= htmlspecialchars($project['location']) ?></p>
        <p><strong>Unit:</strong> <?= htmlspecialchars($unit['name']) ?> |
           <strong>Progress:</strong> <?= $unit['progress'] ?>%</p>
    </div>

    <hr>

    <?php if ($reports && $reports->num_rows > 0): ?>
        <?php while ($r = $reports->fetch_assoc()): ?>
            <div class="report">
                <p class="report-date">📅 <?= date('F d, Y', strtotime($r['report_date'])) ?></p>
                <p><strong>Progress:</strong> <?= $r['progress_percentage'] ?>%</p>
                <p><strong>Work Done:</strong> <?= nl2br(htmlspecialchars($r['work_done'])) ?></p>
                <?php if (!empty($r['remarks'])): ?>
                    <p><strong>Remarks:</strong> <?= nl2br(htmlspecialchars($r['remarks'])) ?></p>
                <?php endif; ?>
                <p class="meta">
                    Created by <?= htmlspecialchars($r['created_by']) ?> |
                    <?= date('M d, Y h:i A', strtotime($r['created_at'])) ?>
                </p>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p><em>No reports available for this unit.</em></p>
    <?php endif; ?>
</main>
</body>
</html>
<?php
$html = ob_get_clean();

// ✅ Generate PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Unit_Report_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $unit['name']) . '.pdf';
$dompdf->stream($filename, ["Attachment" => 0]);
?>
