<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../users/login.php");
    exit();
}

if (!isset($_GET['unit_id']) || !isset($_GET['project_id'])) {
    echo "<h3 style='color:red;'>Invalid parameters.</h3>";
    exit();
}

$unit_id = (int)$_GET['unit_id'];
$project_id = (int)$_GET['project_id'];

// Fetch project and unit details
$project = $conn->query("SELECT * FROM projects WHERE id = $project_id LIMIT 1")->fetch_assoc();
$unit = $conn->query("SELECT * FROM project_units WHERE id = $unit_id LIMIT 1")->fetch_assoc();

if (!$project || !$unit) {
    echo "<h3 style='color:red;'>Data not found.</h3>";
    exit();
}

// Fetch reports for this unit
$query = "SELECT * FROM project_reports WHERE unit_id = $unit_id ORDER BY report_date DESC";
$reports = $conn->query($query);
?>

<div class="content-wrapper">

  <!-- 📁 Header Container -->
  <div class="header-container">
    <div class="reports-header">
      <div>
        <h2><?= htmlspecialchars($unit['name']) ?> — Reports</h2>
        <p class="subtitle">
          Project: <?= htmlspecialchars($project['name']) ?> <br>
          Location: <?= htmlspecialchars($project['location']) ?>
        </p>
      </div>

      <button class="btn-generate" 
        onclick="window.location.href='generate_report_pdf.php?unit_id=<?= $unit_id ?>&project_id=<?= $project_id ?>'">
        Generate Unit PDF
      </button>
    </div>
  </div>

  <!-- 📊 Reports List -->
  <div class="reports-list">
    <?php if ($reports && $reports->num_rows > 0): ?>
      <?php while ($r = $reports->fetch_assoc()): ?>
        <div class="report-card">
          <div class="report-header">
            <h4><?= date('F d, Y', strtotime($r['report_date'])) ?></h4>
            <span class="progress-badge"><?= $r['progress_percentage'] ?>%</span>
          </div>

          <p><strong>Work Done:</strong> <?= nl2br(htmlspecialchars($r['work_done'])) ?></p>

          <?php if (!empty($r['remarks'])): ?>
            <p><strong>Remarks:</strong> <?= nl2br(htmlspecialchars($r['remarks'])) ?></p>
          <?php endif; ?>

          <p class="created-by">Created by: <?= htmlspecialchars($r['created_by']) ?></p>

          <div class="report-actions">
            <a href="view_report.php?id=<?= $r['id'] ?>" class="btn-view">View</a>
            <a href="edit_report.php?id=<?= $r['id'] ?>" class="btn-edit">Edit</a>
            <a href="delete_report.php?id=<?= $r['id'] ?>" 
               onclick="return confirm('Are you sure you want to delete this report?');" 
               class="btn-delete">Delete</a>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="no-data">No reports found for this unit yet.</p>
    <?php endif; ?>
  </div>
</div>

<style>
.content-wrapper {
  padding: 20px;
  background: #f8fafc;
}

/* === Header Container === */
.header-container {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 18px 24px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  margin-bottom: 25px;
  position: sticky;
  top: 0;
  z-index: 100;
}

.reports-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.reports-header h2 {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.subtitle {
  color: #6b7280;
  font-size: 14px;
  margin-top: 4px;
}
.btn-generate {
  background-color: #2563eb;
  color: #fff;
  border: none;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
}
.btn-generate:hover {
  background-color: #1d4ed8;
}

/* === Report Cards === */
.reports-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.report-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 16px 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
  transition: all 0.2s ease;
}
.report-card:hover {
  border-color: #2563eb;
}
.report-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}
.report-header h4 {
  font-size: 16px;
  color: #111827;
  margin: 0;
}
.progress-badge {
  background-color: #2563eb;
  color: #fff;
  padding: 3px 8px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
}
.report-card p {
  font-size: 14px;
  color: #374151;
  margin: 4px 0;
}
.created-by {
  color: #6b7280;
  font-size: 13px;
  margin-top: 8px;
}

/* === Buttons === */
.report-actions {
  display: flex;
  gap: 8px;
  margin-top: 10px;
}
.btn-view, .btn-edit, .btn-delete {
  text-decoration: none;
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  transition: background-color 0.2s;
}
.btn-view {
  background-color: #2563eb;
  color: white;
}
.btn-view:hover { background-color: #1d4ed8; }
.btn-edit {
  background-color: #f59e0b;
  color: white;
}
.btn-edit:hover { background-color: #d97706; }
.btn-delete {
  background-color: #ef4444;
  color: white;
}
.btn-delete:hover { background-color: #dc2626; }

.no-data {
  text-align: center;
  color: #6b7280;
  font-style: italic;
}
</style>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
