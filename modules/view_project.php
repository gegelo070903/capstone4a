<?php
// view_project.php (revised)
// Shows a single project with correct, per-project checklist filtering.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
date_default_timezone_set('Asia/Manila');

// -------- 1) Input & project ----------
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    exit('<p class="error">Invalid project id.</p>');
}
$project_id = (int) $_GET['id'];

$stm = $conn->prepare("
    SELECT p.*, u.username AS constructor_name
    FROM projects p
    LEFT JOIN users u ON u.id = p.constructor_id
    WHERE p.id = ?
    LIMIT 1
");
$stm->bind_param('i', $project_id);
$stm->execute();
$project = $stm->get_result()->fetch_assoc();
$stm->close();
if (!$project) {
    exit('<p class="error">Project not found.</p>');
}

// Is current user the assigned constructor?
$is_assigned_constructor = (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$project['constructor_id']);

// -------- 2) Units for this project ----------
$units = [];
$stm = $conn->prepare("SELECT id, name, description FROM project_units WHERE project_id = ? ORDER BY id ASC");
$stm->bind_param('i', $project_id);
$stm->execute();
$resUnits = $stm->get_result();
while ($row = $resUnits->fetch_assoc()) {
    $units[(int)$row['id']] = $row;
}
$stm->close();

// -------- 3) Checklist items (STRICTLY per project) ----------
$general_items = []; // unit_id IS NULL
$by_unit = [];       // unit_id => [ items... ]

// 3a. General (no unit) items for THIS project
$stm = $conn->prepare("
    SELECT pc.*, uu.username AS completed_by_username
    FROM project_checklists pc
    LEFT JOIN users uu ON uu.id = pc.completed_by_user_id
    WHERE pc.project_id = ? AND pc.unit_id IS NULL
    ORDER BY pc.created_at ASC, pc.id ASC
");
$stm->bind_param('i', $project_id);
$stm->execute();
$resGen = $stm->get_result();
while ($r = $resGen->fetch_assoc()) { $general_items[] = $r; }
$stm->close();

// 3b. Per-unit items for THIS project
$stm = $conn->prepare("
    SELECT pc.*, uu.username AS completed_by_username
    FROM project_checklists pc
    LEFT JOIN users uu ON uu.id = pc.completed_by_user_id
    WHERE pc.project_id = ? AND pc.unit_id IS NOT NULL
    ORDER BY pc.unit_id ASC, pc.created_at ASC, pc.id ASC
");
$stm->bind_param('i', $project_id);
$stm->execute();
$resUnitItems = $stm->get_result();
while ($r = $resUnitItems->fetch_assoc()) {
    $uid = (int)$r['unit_id'];
    if (!isset($by_unit[$uid])) $by_unit[$uid] = [];
    $by_unit[$uid][] = $r;
}
$stm->close();

// -------- 4) Progress (only this project's items) ----------
$total = 0; $done = 0;
foreach ($general_items as $it) { $total++; if (!empty($it['is_completed'])) $done++; }
foreach ($by_unit as $items) {
    foreach ($items as $it) { $total++; if (!empty($it['is_completed'])) $done++; }
}
$progress_pct = $total ? round(($done / $total) * 100) : 0;

// -------- 5) Materials (kept simple; filter by project_id) ----------
$materials = [];
$stm = $conn->prepare("SELECT * FROM materials WHERE project_id = ? ORDER BY id DESC");
$stm->bind_param('i', $project_id);
$stm->execute();
$resMat = $stm->get_result();
while ($m = $resMat->fetch_assoc()) $materials[] = $m;
$stm->close();

// -------- 6) Reports + material usage (optional; filter by project_id) ----------
$reports = [];
$stm = $conn->prepare("
    SELECT cr.*, u.username AS reporter_name
    FROM construction_reports cr
    LEFT JOIN users u ON u.id = cr.constructor_id
    WHERE cr.project_id = ?
    ORDER BY cr.report_date DESC, cr.id DESC
");
$stm->bind_param('i', $project_id);
$stm->execute();
$resRep = $stm->get_result();
while ($r = $resRep->fetch_assoc()) {
    // fetch usage
    $stmU = $conn->prepare("
        SELECT rmu.quantity_used, m.name AS material_name, m.unit_of_measurement
        FROM report_material_usage rmu
        JOIN materials m ON m.id = rmu.material_id
        WHERE rmu.report_id = ?
    ");
    $stmU->bind_param('i', $r['id']);
    $stmU->execute();
    $r['materials'] = $stmU->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmU->close();
    $reports[] = $r;
}
$stm->close();

// -------- 7) Flash/status messages ----------
$status_message = '';
if (isset($_GET['status'])) {
    $map = [
        'report_added_success'           => '<div class="alert success">Daily report added successfully!</div>',
        'report_added_error'             => '<div class="alert error">'. (isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error adding daily report.') .'</div>',
        'project_updated_success'        => '<div class="alert success">Project updated successfully!</div>',
        'checklist_item_added_success'   => '<div class="alert success">Checklist item added successfully!</div>',
        'checklist_item_added_error'     => '<div class="alert error">'. (isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error adding checklist item.') .'</div>',
        'checklist_item_updated_success' => '<div class="alert success">Checklist item updated successfully!</div>',
        'checklist_item_updated_error'   => '<div class="alert error">'. (isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error updating checklist item.') .'</div>',
        'checklist_item_deleted_success' => '<div class="alert success">Checklist item deleted successfully!</div>',
        'checklist_item_deleted_error'   => '<div class="alert error">'. (isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error deleting checklist item.') .'</div>',
    ];
    if (isset($map[$_GET['status']])) $status_message = $map[$_GET['status']];
}

// -------- 8) Header / layout ----------
require_once __DIR__ . '/includes/header.php';
?>
<style>
.main-content-wrapper { max-width: 1200px; margin: 0 auto; padding: 24px; }
.alert{padding:10px;border-radius:6px;margin-bottom:12px;font-weight:600;text-align:center}
.alert.success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
.alert.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6fb}
.card{background:#fff;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,.08);padding:20px 24px;margin-bottom:20px}
.project-title{font-size:28px;margin:0 0 6px}
.badge{padding:6px 10px;border-radius:999px;font-weight:700;color:#fff;font-size:12px}
.badge.ongoing{background:#2563eb}.badge.complete{background:#16a34a}.badge.pending{background:#f59e0b}
.section-title{font-size:20px;margin:12px 0 10px}
.progress-outer{height:16px;border-radius:10px;background:#e5e7eb;overflow:hidden}
.progress-inner{height:100%;background:#16a34a;color:#fff;text-align:center;line-height:16px;font-size:12px}
.table{width:100%;border-collapse:collapse;margin-top:10px}
.table th,.table td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left}
.table th{background:#f8fafc;color:#4b5563;font-weight:600}
.unit-title{font-weight:700;margin:12px 0 6px}
.muted{color:#6b7280;font-style:italic}
</style>

<div class="main-content-wrapper">
  <?= $status_message ?>

  <div class="card">
    <h2 class="project-title"><?= h($project['name']) ?></h2>
    <div class="muted" style="margin-bottom:6px;">
      <strong>Status:</strong> <span class="badge <?= strtolower($project['status']) ?>"><?= h($project['status']) ?></span>
      &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Location:</strong> <?= h($project['location']) ?>
      &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Constructor:</strong> <?= h($project['constructor_name']) ?>
      &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Created:</strong> <?= date('M d, Y', strtotime($project['created_at'])) ?>
    </div>
    <?php if (is_admin()): ?>
      <div>
        <a href="edit_project.php?id=<?= $project_id ?>" class="btn">Edit</a>
        <a href="delete_project.php?id=<?= $project_id ?>" class="btn" onclick="return confirm('Delete this project?')">Delete</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- ===== Checklist ===== -->
  <div class="card">
    <h3 class="section-title">Project Milestones / Checklist</h3>
    <div class="muted" style="margin-bottom:6px;">
      Overall Progress: <strong><?= $progress_pct ?>%</strong> (<?= $done ?> of <?= $total ?> completed)
    </div>
    <div class="progress-outer">
      <div class="progress-inner" style="width: <?= $progress_pct ?>%;"><?= $progress_pct ?>%</div>
    </div>

    <?php if ($general_items): ?>
      <div class="unit-title">General Items (no unit)</div>
      <ul style="list-style:none;padding-left:0;margin:6px 0 12px;">
        <?php foreach ($general_items as $it): ?>
          <li style="margin:4px 0;">
            <?= !empty($it['is_completed']) ? '✅' : '⬜' ?>
            <?= h($it['item_description']) ?>
            <?php if (!empty($it['is_completed']) && !empty($it['completed_at'])): ?>
              <span class="muted"> (Completed<?= !empty($it['completed_by_username']) ? ' by '.h($it['completed_by_username']) : '' ?> on <?= date('M d, Y h:i A', strtotime($it['completed_at'])) ?>)</span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="muted">No general items.</div>
    <?php endif; ?>

    <?php foreach ($units as $uid => $u): ?>
      <div class="unit-title">Unit: <?= h($u['name']) ?></div>
      <?php if (!empty($by_unit[$uid])): ?>
        <ul style="list-style:none;padding-left:0;margin:6px 0 12px;">
          <?php foreach ($by_unit[$uid] as $it): ?>
            <li style="margin:4px 0;">
              <?= !empty($it['is_completed']) ? '✅' : '⬜' ?>
              <?= h($it['item_description']) ?>
              <?php if (!empty($it['is_completed']) && !empty($it['completed_at'])): ?>
                <span class="muted"> (Completed<?= !empty($it['completed_by_username']) ? ' by '.h($it['completed_by_username']) : '' ?> on <?= date('M d, Y h:i A', strtotime($it['completed_at'])) ?>)</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="muted">No items.</div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <!-- ===== Materials (simple table) ===== -->
  <div class="card">
    <h3 class="section-title">Materials</h3>
    <?php if ($materials): ?>
      <table class="table">
        <thead>
        <tr><th>Name</th><th>Quantity</th><th>Price</th><th>Total</th><th>Date</th><th>Purpose</th><?php if (is_admin()): ?><th>Action</th><?php endif; ?></tr>
        </thead>
        <tbody>
        <?php foreach ($materials as $m): ?>
          <tr>
            <td><?= h($m['name']) ?></td>
            <td><?= h($m['quantity']).' '.h($m['unit_of_measurement']) ?></td>
            <td>₱<?= number_format((float)$m['price'], 2) ?></td>
            <td>₱<?= number_format((float)$m['total_amount'], 2) ?></td>
            <td><?= h(($m['date'] ?? '') . (isset($m['time']) ? ' '.$m['time'] : '')) ?></td>
            <td><?= h($m['purpose']) ?></td>
            <?php if (is_admin()): ?>
              <td>
                <a href="edit_material.php?id=<?= (int)$m['id'] ?>">Edit</a>
                <a href="delete_material.php?id=<?= (int)$m['id'] ?>" onclick="return confirm('Delete this material?')">Delete</a>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="muted">No materials added yet.</div>
    <?php endif; ?>
  </div>

  <!-- ===== Reports (optional) ===== -->
  <div class="card">
    <h3 class="section-title">Daily Development Reports</h3>
    <?php if ($reports): ?>
      <table class="table">
        <thead>
          <tr><th>Date</th><th>Time</th><th>Status</th><th>Description</th><th>Materials Used</th><th>Reporter</th><th>Proof</th><?php if (is_admin()): ?><th>Action</th><?php endif; ?></tr>
        </thead>
        <tbody>
          <?php foreach ($reports as $rp): ?>
          <tr>
            <td><?= date('m-d-Y', strtotime($rp['report_date'])) ?></td>
            <td><?= date('h:i A', strtotime($rp['start_time'])) ?> - <?= date('h:i A', strtotime($rp['end_time'])) ?></td>
            <td><span class="badge <?= strtolower($rp['status']) ?>"><?= h($rp['status']) ?></span></td>
            <td><?= h($rp['description']) ?></td>
            <td>
              <?php if (!empty($rp['materials'])): ?>
                <ul style="margin:0;padding-left:18px;">
                  <?php foreach ($rp['materials'] as $mu): ?>
                    <li><?= h($mu['quantity_used']).' '.h($mu['unit_of_measurement']).' of '.h($mu['material_name']) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>N/A<?php endif; ?>
            </td>
            <td><?= h($rp['reporter_name']) ?></td>
            <td><?= !empty($rp['proof_image']) ? '<a href="'.h($rp['proof_image']).'" target="_blank">View</a>' : 'N/A' ?></td>
            <?php if (is_admin()): ?>
              <td>
                <a href="edit_development_report.php?id=<?= (int)$rp['id'] ?>">Edit</a>
                <a href="delete_development_report.php?id=<?= (int)$rp['id'] ?>" onclick="return confirm('Delete this report?')">Delete</a>
              </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="muted">No development reports yet.</div>
    <?php endif; ?>
  </div>

</div>

<script>
// Optional: fade alerts
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.alert').forEach(a=>{
    setTimeout(()=>{ a.style.opacity = '0'; a.addEventListener('transitionend',()=>a.remove()); }, 4000);
  });
});
</script>
</body>
</html>