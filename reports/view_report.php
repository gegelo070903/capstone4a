<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_GET['id'])) {
  echo "<div class='container'><p>Invalid Report ID.</p></div>";
  require_once __DIR__ . '/../includes/footer.php';
  exit;
}

$report_id = (int)$_GET['id'];

// Fetch report info
$stmt = $conn->prepare("
  SELECT r.*, p.name AS project_name 
  FROM project_reports r
  JOIN projects p ON r.project_id = p.id
  WHERE r.id = ?
");
$stmt->bind_param('i', $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
  echo "<div class='container'><p>Report not found.</p></div>";
  require_once __DIR__ . '/../includes/footer.php';
  exit;
}

// Fetch materials used
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
// ✅ UPDATED QUERY: Assuming database column is now 'image_path'
$imgStmt = $conn->prepare("SELECT image_path, uploaded_at FROM report_images WHERE report_id = ?");
$imgStmt->bind_param('i', $report_id);
$imgStmt->execute();
$images = $imgStmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="main-content-wrapper">
  <div class="project-header-card">
    <div class="header-row">
      <h2>Project Report — <?= htmlspecialchars($report['project_name']); ?></h2>
      <a href="/capstone/modules/view_project.php?id=<?= $report['project_id']; ?>" class="btn-back">← Back</a>
    </div>

    <div class="report-details">
      <p><strong>Date:</strong> <?= date('m-d-Y', strtotime($report['report_date'])); ?></p>
      <p><strong>Progress:</strong> <?= intval($report['progress_percentage']); ?>%</p>
      <p><strong>Work Done:</strong> <?= nl2br(htmlspecialchars($report['work_done'])); ?></p>
      <p><strong>Remarks:</strong> <?= nl2br(htmlspecialchars($report['remarks'] ?? '—')); ?></p>
      <p><strong>Created by:</strong> <?= htmlspecialchars($report['created_by']); ?></p>
      <p><strong>Created at:</strong> <?= date('m-d-Y h:i A', strtotime($report['created_at'])); ?></p>
    </div>
  </div>

  <!-- ✅ REPLACED BLOCK: Old tab-content replaced by project-details-card -->
  <div class="project-details-card">
    <?php if (!empty($materials)): ?>
    <h3 class="section-title">Materials Used</h3>
    <table class="styled-table">
      <thead>
        <tr>
          <th>Material</th>
          <th>Quantity Used</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($materials as $m): ?>
          <tr>
            <td><?= htmlspecialchars($m['name']); ?></td>
            <td><?= htmlspecialchars($m['quantity_used']) . ' ' . htmlspecialchars($m['unit_of_measurement']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (!empty($images)): ?>
    <h3 class="section-title">Proof Images</h3>
    <div class="image-grid">
      <?php foreach ($images as $img): 
        $path = '/capstone/reports/report_images/' . $img['image_path']; ?>
        <div class="image-item">
          <img src="<?= htmlspecialchars($path); ?>" alt="Proof Image">
          <small>Uploaded: <?= date('m-d-Y h:i A', strtotime($img['uploaded_at'])); ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  </div>
</div>

<style>
/* ✅ UPDATED: Padding of main container set to 10px */
.main-content-wrapper {
  padding: 10px;
  background: #f8f9fc;
  min-height: 100vh;
}

.project-header-card {
  background: #fff;
  padding: 25px;
  border-radius: 10px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.08);
  margin-bottom: 25px;
}

/* ✅ NEW STYLE */
.project-details-card {
  background: #fff;
  padding: 25px;
  border-radius: 10px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.08);
  margin-top: 20px;
  margin-bottom: 25px;
}

.header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.btn-back {
  background: #374151;
  color: #fff;
  text-decoration: none;
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 600;
  transition: background 0.2s ease;
}
.btn-back:hover {
  background: #111827;
}

.report-details p {
  margin: 6px 0;
  font-size: 15px;
  color: #333;
}

.section-title {
  font-size: 18px;
  font-weight: 600;
  color: #1e3a8a;
  margin: 20px 0 10px;
}

.styled-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
}
.styled-table th, .styled-table td {
  border: 1px solid #ddd;
  padding: 10px;
}
.styled-table th {
  background: #f1f5f9;
  text-align: left;
}

.image-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  margin-top: 10px;
}
.image-item {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px;
  width: 180px;
  text-align: center;
  box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.image-item img {
  width: 100%;
  height: 120px;
  object-fit: cover;
  border-radius: 6px;
}
.image-item small {
  display: block;
  color: #555;
  font-size: 12px;
  margin-top: 4px;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>