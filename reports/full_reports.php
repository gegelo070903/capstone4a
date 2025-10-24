<?php
// ===========================================================
// full_reports.php — Synced and Generalized Report View
// ===========================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
date_default_timezone_set('Asia/Manila');

// 1️⃣ Validate project ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    exit('<p class="error">Invalid project ID.</p>');
}
$project_id = (int)$_GET['id'];

// 2️⃣ Get project details
$stmt = $conn->prepare("
    SELECT p.*, u.username AS constructor_name
    FROM projects p
    LEFT JOIN users u ON u.id = p.constructor_id
    WHERE p.id = ?
");
$stmt->bind_param('i', $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) {
    exit('<p class="error">Project not found.</p>');
}

// 3️⃣ Fetch units
$units = [];
$res = $conn->query("SELECT id, name FROM project_units WHERE project_id = $project_id ORDER BY id ASC");
while ($row = $res->fetch_assoc()) $units[$row['id']] = $row;

// 4️⃣ Fetch checklists
$general = [];
$per_unit = [];
$res = $conn->query("
    SELECT c.*, u.username AS completed_by_username 
    FROM project_checklists c 
    LEFT JOIN users u ON u.id = c.completed_by_user_id
    WHERE c.project_id = $project_id
    ORDER BY c.unit_id ASC, c.id ASC
");
while ($r = $res->fetch_assoc()) {
    if (empty($r['unit_id'])) $general[] = $r;
    else $per_unit[$r['unit_id']][] = $r;
}

// 5️⃣ Compute progress
$total = 0; $done = 0;
foreach ($general as $g) { $total++; if ($g['is_completed']) $done++; }
foreach ($per_unit as $items) {
    foreach ($items as $i) { $total++; if ($i['is_completed']) $done++; }
}
$progress = $total ? round(($done / $total) * 100) : 0;

// 6️⃣ Fetch materials
$materials = $conn->query("SELECT * FROM materials WHERE project_id = $project_id ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// 7️⃣ Fetch reports
$reports = [];
$res = $conn->query("
    SELECT r.*, u.username AS reporter_name 
    FROM construction_reports r
    LEFT JOIN users u ON u.id = r.constructor_id
    WHERE r.project_id = $project_id
    ORDER BY r.report_date DESC
");
while ($r = $res->fetch_assoc()) {
    $rmu = $conn->query("
        SELECT rm.quantity_used, m.name, m.unit_of_measurement
        FROM report_material_usage rm
        JOIN materials m ON m.id = rm.material_id
        WHERE rm.report_id = {$r['id']}
    ");
    $r['materials_used'] = $rmu->fetch_all(MYSQLI_ASSOC);
    $reports[] = $r;
}
require_once __DIR__ . '/includes/header.php';
?>

<style>
body { background-color: #f9fafb; font-family: "Segoe UI", sans-serif; }
.container { max-width: 1100px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 25px rgba(0,0,0,0.05); }
.section { margin-top: 30px; }
h2, h3, h4 { color: #1f2937; margin-bottom: 10px; }
.progress-container { background: #e5e7eb; height: 18px; border-radius: 10px; overflow: hidden; }
.progress-bar { background: #16a34a; color: white; text-align: center; height: 100%; font-size: 12px; line-height: 18px; }
.table { width: 100%; border-collapse: collapse; margin-top: 10px; }
.table th, .table td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
.table th { background: #f3f4f6; }
.muted { color: #6b7280; font-style: italic; }
</style>

<div class="container">
  <h2><?= htmlspecialchars($project['name']) ?></h2>
  <p>
    <strong>Status:</strong> <?= htmlspecialchars($project['status']) ?> |
    <strong>Location:</strong> <?= htmlspecialchars($project['location']) ?> |
    <strong>Constructor:</strong> <?= htmlspecialchars($project['constructor_name']) ?> |
    <strong>Created:</strong> <?= date('M d, Y', strtotime($project['created_at'])) ?>
  </p>

  <div class="section">
    <h3>Project Milestones / Checklist</h3>
    <p>Progress: <strong><?= $progress ?>%</strong> (<?= $done ?> / <?= $total ?>)</p>
    <div class="progress-container"><div class="progress-bar" style="width: <?= $progress ?>%;"><?= $progress ?>%</div></div>

    <h4>General Items</h4>
    <?php if ($general): ?>
      <ul>
        <?php foreach ($general as $i): ?>
          <li><?= $i['is_completed'] ? '✅' : '⬜' ?> <?= htmlspecialchars($i['item_description']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?><p class="muted">No general checklist items.</p><?php endif; ?>

    <?php foreach ($units as $uid => $unit): ?>
      <h4>Unit: <?= htmlspecialchars($unit['name']) ?></h4>
      <?php if (!empty($per_unit[$uid])): ?>
        <ul>
          <?php foreach ($per_unit[$uid] as $i): ?>
            <li><?= $i['is_completed'] ? '✅' : '⬜' ?> <?= htmlspecialchars($i['item_description']) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?><p class="muted">No items for this unit.</p><?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div class="section">
    <h3>Materials Acquired</h3>
    <?php if ($materials): ?>
      <table class="table">
        <thead><tr><th>Name</th><th>Quantity</th><th>Supplier</th><th>Total</th><th>Purpose</th></tr></thead>
        <tbody>
        <?php foreach ($materials as $m): ?>
          <tr>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><?= htmlspecialchars($m['quantity']) . ' ' . htmlspecialchars($m['unit_of_measurement']) ?></td>
            <td><?= htmlspecialchars($m['supplier']) ?></td>
            <td>₱<?= number_format($m['total_amount'], 2) ?></td>
            <td><?= htmlspecialchars($m['purpose']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?><p class="muted">No materials yet.</p><?php endif; ?>
  </div>

  <div class="section">
    <h3>Daily Reports</h3>
    <?php if ($reports): ?>
      <table class="table">
        <thead><tr><th>Date</th><th>Status</th><th>Description</th><th>Reporter</th><th>Proof</th></tr></thead>
        <tbody>
        <?php foreach ($reports as $r): ?>
          <tr>
            <td><?= date('M d, Y', strtotime($r['report_date'])) ?></td>
            <td><?= htmlspecialchars($r['status']) ?></td>
            <td><?= htmlspecialchars($r['description']) ?></td>
            <td><?= htmlspecialchars($r['reporter_name']) ?></td>
            <td><?= $r['proof_image'] ? "<a href='{$r['proof_image']}' target='_blank'>View</a>" : "N/A" ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?><p class="muted">No reports yet.</p><?php endif; ?>
  </div>
</div>
</body>
</html>
