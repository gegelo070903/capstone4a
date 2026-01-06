<?php
ob_start();
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['username'])) {
    header('Location: /capstone/users/login.php');
    exit;
}

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch report data
$stmt = $conn->prepare("SELECT * FROM project_reports WHERE id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    echo '<div class="container"><p class="text-red-600">Report not found.</p></div>';
    require_once __DIR__ . '/../includes/footer.php';
    ob_end_flush();
    exit;
}

$project_id = $report['project_id'];

// Fetch project name
$pq = $conn->prepare("SELECT name FROM projects WHERE id = ?");
$pq->bind_param("i", $project_id);
$pq->execute();
$project_name = $pq->get_result()->fetch_assoc()['name'] ?? 'Unknown Project';
$pq->close();

// Fetch unit name
$uq = $conn->prepare("SELECT name FROM project_units WHERE id = ?");
$uq->bind_param("i", $report['unit_id']);
$uq->execute();
$unit_name = $uq->get_result()->fetch_assoc()['name'] ?? 'N/A';
$uq->close();

// Fetch proof images
$images = [];
$img_stmt = $conn->prepare("SELECT id, image_path FROM report_images WHERE report_id = ?");
$img_stmt->bind_param("i", $report_id);
$img_stmt->execute();
$res = $img_stmt->get_result();
while ($r = $res->fetch_assoc()) $images[] = $r;
$img_stmt->close();

// Determine back URL based on where the user came from
$from = $_GET['from'] ?? '';
$unit_id_param = $_GET['unit_id'] ?? $report['unit_id'];
$project_id_param = $_GET['project_id'] ?? $project_id;

if ($from === 'reports') {
    $back_url = "view_unit_reports.php?unit_id=" . (int)$unit_id_param . "&project_id=" . (int)$project_id_param;
} else {
    $back_url = "../modules/view_project.php?id=" . (int)$project_id . "&tab=reports";
}

// Build query params to pass to edit/delete for proper navigation
$nav_params = ($from === 'reports') ? "&from=reports&unit_id=" . (int)$unit_id_param . "&project_id=" . (int)$project_id_param : "";
?>

<div class="content-wrapper">
  <div class="form-card">
    <div class="form-header-row">
      <h2>Project Report — <?= htmlspecialchars($project_name) ?></h2>
      <a href="<?= $back_url ?>" class="btn-back-black">← Back</a>
    </div>

    <div class="report-details">
      <p><strong>Date:</strong> <?= htmlspecialchars(date('F d, Y', strtotime($report['report_date']))) ?></p>
      <p><strong>Unit:</strong> <?= htmlspecialchars($unit_name) ?></p>
      <p><strong>Progress:</strong> <?= (int)$report['progress_percentage'] ?>%</p>
      <p><strong>Created By:</strong> <?= htmlspecialchars($report['created_by']) ?></p>
      <p><strong>Remarks:</strong> <?= nl2br(htmlspecialchars($report['remarks'])) ?></p>

      <div class="section-divider"></div>

      <h4>Work Done</h4>
      <p><?= nl2br(htmlspecialchars($report['work_done'])) ?></p>

      <div class="section-divider"></div>

      <h4>Proof Images</h4>
      <?php if (!empty($images)): ?>
        <div class="image-grid">
          <?php foreach ($images as $img): ?>
            <div class="img-item">
              <img src="report_images/<?= htmlspecialchars($img['image_path']) ?>" alt="Proof Image" class="proof-img">
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="color:#888;">No proof images uploaded.</p>
      <?php endif; ?>

      <div class="section-divider"></div>

      <div class="form-actions">
        <a href="edit_report.php?id=<?= $report_id ?><?= $nav_params ?>" class="btn-primary">Edit Report</a>
        <a href="delete_report.php?id=<?= $report_id ?><?= $nav_params ?>" class="btn-danger"
           onclick="return confirm('Are you sure you want to delete this report? This action cannot be undone.');">
           Delete Report
        </a>
      </div>
    </div>
  </div>
</div>

<style>
/* === Base Layout === */
.content-wrapper {
  padding: 5px 5px 10px 10px;

.form-card {
  background: #fff;
  border-radius: 12px;
  padding: 20px 25px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.05);
  max-width: 800px;
  margin: 0 auto;
}

/* === Header === */
.form-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}
.form-header-row h2 {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 0;
}

/* === Back Button === */
.btn-back-black {
  background-color: #1f2937;
  color: #fff;
  font-weight: 600;
  border: none;
  border-radius: 6px;
  padding: 6px 14px;
  text-decoration: none;
  transition: background 0.2s ease;
}
.btn-back-black:hover {
  background-color: #374151;
}

/* === Details Section === */
.report-details p {
  margin: 5px 0;
  color: #1f2937;
  font-size: 14px;
}

/* === Section Divider === */
.section-divider {
  border-bottom: 1px solid #e5e7eb;
  margin: 15px 0;
}

/* === Proof Images === */
.image-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 10px;
}
.proof-img {
  width: 150px;
  height: 100px;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid #d1d5db;
}

/* === Buttons === */
.btn-primary {
  background-color: #2563eb;
  color: white;
  font-weight: 600;
  border: none;
  border-radius: 6px;
  padding: 8px 16px;
  text-decoration: none;
  cursor: pointer;
  transition: background-color 0.2s ease;
}
.btn-primary:hover {
  background-color: #1d4ed8;
}

.btn-danger {
  background-color: #dc2626;
  color: white;
  font-weight: 600;
  border: none;
  border-radius: 6px;
  padding: 8px 16px;
  text-decoration: none;
  cursor: pointer;
  transition: background-color 0.2s ease;
}
.btn-danger:hover {
  background-color: #b91c1c;
}

/* === Footer Buttons === */
.form-actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  margin-top: 20px;
  gap: 10px;
}
</style>

<?php 
require_once __DIR__ . '/../includes/footer.php';
ob_end_flush();
?>