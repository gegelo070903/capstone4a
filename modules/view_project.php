<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit("<h3>Invalid project ID.</h3>");
}
$project_id = intval($_GET['id']);

$project = $conn->query("SELECT * FROM projects WHERE id = $project_id")->fetch_assoc();
if (!$project) exit("<h3>Project not found.</h3>");

// Units (houses)
$units = $conn->query("SELECT * FROM project_units WHERE project_id = $project_id ORDER BY id ASC");

// Checklist items
$checklists = $conn->query("SELECT * FROM project_checklists WHERE project_id = $project_id ORDER BY id ASC");

// Materials
$materials = $conn->query("SELECT * FROM materials WHERE project_id = $project_id ORDER BY id DESC");

// Daily reports
$reports = $conn->query("
    SELECT * FROM construction_reports 
    WHERE project_id = $project_id 
    ORDER BY report_date DESC
");
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.main-content-wrapper { padding: 25px; background: #f8fafc; min-height: 100vh; }
.card {
    background: #fff;
    border-radius: 12px;
    padding: 25px 30px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}
.card h3 { margin-top: 0; color: #111827; }

.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
.table th { background: #f3f4f6; font-weight: 600; color: #374151; }
.badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; color: #fff; }
.badge.Pending { background-color: #fbbf24; }
.badge.Ongoing { background-color: #3b82f6; }
.badge.Completed { background-color: #22c55e; }
.btn {
    background-color: #2563eb; color: #fff; padding: 8px 16px;
    border-radius: 6px; text-decoration: none; font-size: 14px;
    font-weight: 600; margin-right: 6px; display: inline-block;
}
.btn:hover { background-color: #1d4ed8; }
</style>

<div class="main-content-wrapper">

  <div class="card">
    <h2><?= htmlspecialchars($project['name']) ?></h2>
    <p>
      <strong>Status:</strong> 
      <span class="badge <?= htmlspecialchars($project['status']) ?>"><?= htmlspecialchars($project['status']) ?></span> &nbsp;|
      <strong>Location:</strong> <?= htmlspecialchars($project['location']) ?> &nbsp;|
      <strong>Units:</strong> <?= htmlspecialchars($project['units']) ?> &nbsp;|
      <strong>Created:</strong> <?= date('M d, Y', strtotime($project['created_at'])) ?>
    </p>

    <?php if (is_admin()): ?>
      <a href="edit_project.php?id=<?= $project_id ?>" class="btn">Edit</a>
      <a href="delete_project.php?id=<?= $project_id ?>" class="btn" style="background-color:#dc2626;">Delete</a>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>🏠 Unit Checklist</h3>
    <?php if ($checklists->num_rows > 0): ?>
      <table class="table">
        <thead>
          <tr><th>Unit</th><th>Task</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php while ($c = $checklists->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($c['unit_id'] ?? '-') ?></td>
            <td><?= htmlspecialchars($c['item_description']) ?></td>
            <td><?= $c['is_completed'] ? '✅ Completed' : '⬜ Pending' ?></td>
            <td><?= $c['completed_at'] ? date('M d, Y', strtotime($c['completed_at'])) : '-' ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p style="color:#6b7280;">No checklist items found.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>📦 Materials Used</h3>
    <?php if ($materials->num_rows > 0): ?>
      <table class="table">
        <thead>
          <tr><th>Name</th><th>Quantity</th><th>Unit</th><th>Purpose</th></tr>
        </thead>
        <tbody>
        <?php while ($m = $materials->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><?= htmlspecialchars($m['quantity']) ?></td>
            <td><?= htmlspecialchars($m['unit_of_measurement']) ?></td>
            <td><?= htmlspecialchars($m['purpose']) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p style="color:#6b7280;">No materials added yet.</p>
    <?php endif; ?>

    <a href="../modules/add_material.php?project_id=<?= $project_id ?>" class="btn">+ Add Material</a>
  </div>

  <div class="card">
    <h3>📅 Daily Reports</h3>
    <?php if ($reports->num_rows > 0): ?>
      <table class="table">
        <thead>
          <tr><th>Date</th><th>Status</th><th>Description</th><th>Materials Used</th></tr>
        </thead>
        <tbody>
        <?php while ($r = $reports->fetch_assoc()): ?>
          <?php
          $materials_used = $conn->query("
            SELECT m.name, rmu.quantity_used, m.unit_of_measurement
            FROM report_material_usage rmu
            JOIN materials m ON m.id = rmu.material_id
            WHERE rmu.report_id = {$r['id']}
          ");
          ?>
          <tr>
            <td><?= date('M d, Y', strtotime($r['report_date'])) ?></td>
            <td><span class="badge <?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            <td><?= htmlspecialchars($r['description']) ?></td>
            <td>
              <?php if ($materials_used->num_rows > 0): ?>
                <ul style="margin:0;padding-left:18px;">
                  <?php while ($mu = $materials_used->fetch_assoc()): ?>
                    <li><?= htmlspecialchars($mu['quantity_used'].' '.$mu['unit_of_measurement'].' '.$mu['name']) ?></li>
                  <?php endwhile; ?>
                </ul>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p style="color:#6b7280;">No daily reports yet.</p>
    <?php endif; ?>

    <a href="../modules/add_report.php?project_id=<?= $project_id ?>" class="btn">+ Add Daily Report</a>
  </div>
</div>

</body>
</html>
