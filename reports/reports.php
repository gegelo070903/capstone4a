<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['username'])) {
    header('Location: /capstone/users/login.php');
    exit;
}

// Fetch all reports grouped by project
$sql = "
    SELECT 
        p.id AS project_id,
        p.name AS project_name,
        r.id AS report_id,
        r.report_date,
        r.progress_percentage,
        r.created_by,
        r.created_at
    FROM project_reports r
    JOIN projects p ON r.project_id = p.id
    ORDER BY p.name ASC, r.report_date DESC
";
$res = $conn->query($sql);

// Group results by project
$projects = [];
while ($row = $res->fetch_assoc()) {
    $projects[$row['project_name']][] = $row;
}
?>

<div class="container" style="max-width: 1000px; margin: 20px auto;">
    <h2>📁 All Project Reports (Grouped by Project)</h2>

    <?php if (!empty($projects)): ?>
        <?php foreach ($projects as $projectName => $reports): ?>
            <div style="margin-top: 25px; border:1px solid #ddd; border-radius:6px; padding:15px; background:#fafafa;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0;"><?= htmlspecialchars($projectName) ?></h3>
                    <a href="/capstone/reports/generate_project_pdf.php?project_id=<?= $reports[0]['project_id'] ?>" 
                       style="background:#007bff; color:white; padding:6px 12px; border-radius:4px; text-decoration:none;">
                       🖨️ Generate Project PDF
                    </a>
                </div>

                <table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse; margin-top:15px;">
                    <thead style="background:#f2f2f2;">
                        <tr>
                            <th>Date</th>
                            <th>Progress</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('m-d-Y', strtotime($r['report_date']))) ?></td>
                                <td><?= htmlspecialchars($r['progress_percentage']) ?>%</td>
                                <td><?= htmlspecialchars($r['created_by']) ?></td>
                                <td><?= htmlspecialchars(date('m-d-Y h:i A', strtotime($r['created_at']))) ?></td>
                                <td>
                                    <a href="/capstone/reports/view_report.php?id=<?= $r['report_id'] ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="margin-top:20px;">No reports found.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
