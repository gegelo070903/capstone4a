<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

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

// ✅ Prepare HTML for PDF
ob_start();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($unit['name']) ?> - Report</title>
<style>
    body {
        font-family: 'DejaVu Sans', sans-serif;
        margin: 40px;
        color: #000;
        font-size: 12pt;
    }

    /* HEADER */
    .header {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .header img {
        width: 85px;
        height: auto;
        margin-right: 12px;
    }

    .header-text {
        text-align: left;
        line-height: 1.4;
    }

    .header-text h1 {
        font-size: 16pt;
        margin: 0;
        color: #000;
    }

    .header-text p {
        font-size: 10pt;
        margin: 2px 0;
    }

    /* UNIT SUMMARY */
    .unit-summary {
        text-align: center;
        margin-bottom: 20px;
    }

    .unit-summary h2 {
        font-size: 15pt;
        color: #000;
        margin-bottom: 6px;
    }

    .unit-summary h3 {
        font-size: 13pt;
        margin-bottom: 4px;
    }

    .unit-summary p {
        font-size: 11pt;
        margin: 3px 0;
    }

    /* REPORT CARD */
    .report {
        border: 1px solid #999;
        border-radius: 6px;
        background-color: #fafafa;
        padding: 10px 15px;
        margin-bottom: 15px;
    }

    .report-date {
        font-weight: bold;
        color: #000;
        font-size: 11pt;
        margin-bottom: 5px;
    }

    .report strong {
        color: #000;
    }

    .meta {
        font-size: 10pt;
        color: #555;
        margin-top: 3px;
    }

    hr {
        border: none;
        border-top: 1px solid #000;
        margin: 10px 0 15px 0;
    }

    .footer {
        text-align: right;
        font-size: 9pt;
        color: #444;
        margin-top: 30px;
        border-top: 1px solid #ccc;
        padding-top: 5px;
    }
</style>
</head>
<body>

    <!-- HEADER -->
    <div class="header">
        <img src="<?= realpath(__DIR__ . '/../assets/images/logo.png') ?>" alt="Company Logo">
        <div class="header-text">
            <h1>Sunshine Sapphire Construction and Supply, Inc.</h1>
            <p>St. Mark Ave., Doña Juliana Subd.</p>
            <p>Brgy. Taculing, Bacolod City</p>
            <p>Negros Occidental</p>
            <p>Email: sunshinesapphire19@gmail.com | rowenatupas65@yahoo.com</p>
            <p>Tel: 0943-130-1714</p>
        </div>
    </div>

    <!-- UNIT SUMMARY -->
    <div class="unit-summary">
        <h2>Unit Report</h2>
        <h3><?= htmlspecialchars($project['name']) ?></h3>
        <p>Location: <?= htmlspecialchars($project['location']) ?></p>
        <p><strong>Unit:</strong> <?= htmlspecialchars($unit['name']) ?> |
           <strong>Progress:</strong> <?= $unit['progress'] ?>%</p>
    </div>

    <hr>

    <!-- REPORT LIST -->
    <?php if ($reports && $reports->num_rows > 0): ?>
        <?php while ($r = $reports->fetch_assoc()): ?>
            <div class="report">
                <p class="report-date"><?= date('F d, Y', strtotime($r['report_date'])) ?></p>
                <p><strong>Progress:</strong> <?= $r['progress_percentage'] ?>%</p>
                <p><strong>Work Done:</strong> <?= nl2br(htmlspecialchars($r['work_done'])) ?></p>
                <?php if (!empty($r['remarks'])): ?>
                    <p><strong>Remarks:</strong> <?= nl2br(htmlspecialchars($r['remarks'])) ?></p>
                <?php endif; ?>
                <p class="meta">Created by <?= htmlspecialchars($r['created_by']) ?> |
                <?= date('M d, Y h:i A', strtotime($r['created_at'])) ?></p>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p><em>No reports available for this unit.</em></p>
    <?php endif; ?>

    <!-- FOOTER -->
    <div class="footer">
        Generated on <?= date('F d, Y h:i A') ?>
    </div>

</body>
</html>

<?php
$html = ob_get_clean();

// ✅ Initialize mPDF
$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_top' => 20,
    'margin_bottom' => 15,
    'margin_left' => 15,
    'margin_right' => 15,
]);

$mpdf->WriteHTML($html);
$filename = 'Unit_Report_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $unit['name']) . '.pdf';
$mpdf->Output($filename, 'I'); // 'I' = inline view
?>
