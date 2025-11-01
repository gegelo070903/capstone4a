<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

// ✅ Get report ID
$report_id = $_GET['id'] ?? null;
if (!$report_id) {
  die('Report not specified.');
}

// ✅ Fetch report info
$stmt = $conn->prepare("SELECT * FROM project_reports WHERE id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
  die('Report not found.');
}

$project_id = $report['project_id'];

// ✅ Fetch related materials and images
$materials = $conn->query("SELECT * FROM project_report_materials WHERE report_id = $report_id");
$images = $conn->query("SELECT * FROM project_report_images WHERE report_id = $report_id");

// ✅ Update report
if (isset($_POST['update_report'])) {
  $report_date = $_POST['report_date'];
  $progress_percentage = $_POST['progress_percentage'];
  $work_done = $_POST['work_done'];
  $remarks = $_POST['remarks'];

  $update_stmt = $conn->prepare("
    UPDATE project_reports 
    SET report_date=?, progress_percentage=?, work_done=?, remarks=?, updated_at=NOW() 
    WHERE id=?
  ");
  $update_stmt->bind_param("sissi", $report_date, $progress_percentage, $work_done, $remarks, $report_id);
  $update_stmt->execute();

  // ✅ Update materials
  if (isset($_POST['material_name'])) {
    $conn->query("DELETE FROM project_report_materials WHERE report_id = $report_id");
    foreach ($_POST['material_name'] as $index => $name) {
      $quantity = $_POST['material_quantity'][$index];
      if (!empty(trim($name))) {
        $m_stmt = $conn->prepare("INSERT INTO project_report_materials (report_id, material_name, quantity_used) VALUES (?, ?, ?)");
        $m_stmt->bind_param("iss", $report_id, $name, $quantity);
        $m_stmt->execute();
      }
    }
  }

  // ✅ Handle proof images upload
  if (!empty($_FILES['proof_images']['name'][0])) {
    $uploadDir = "../uploads/report_images/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    foreach ($_FILES['proof_images']['tmp_name'] as $key => $tmpName) {
      $fileName = basename($_FILES['proof_images']['name'][$key]);
      $targetPath = $uploadDir . $fileName;

      if (move_uploaded_file($tmpName, $targetPath)) {
        $conn->query("INSERT INTO project_report_images (report_id, file_path) VALUES ($report_id, '$targetPath')");
      }
    }
  }

  header("Location: ../modules/view_project.php?id=$project_id");
  exit;
}

// ✅ Delete proof image
if (isset($_GET['delete_image'])) {
  $img_id = intval($_GET['delete_image']);
  $conn->query("DELETE FROM project_report_images WHERE id = $img_id");
  header("Location: edit_report.php?id=$report_id");
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Report</title>
  <style>
    body {
      background: #f8f9fc;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 0;
    }

    .content-wrapper {
      padding: 30px;
      max-width: 900px;
      margin: auto;
    }

    .form-card {
      background: #fff;
      border-radius: 10px;
      padding: 30px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.08);
    }

    h2 {
      font-size: 22px;
      margin-bottom: 20px;
      color: #111827;
      font-weight: 700;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      margin-bottom: 18px;
    }

    label {
      font-weight: 600;
      color: #374151;
      margin-bottom: 5px;
    }

    input[type="text"],
    input[type="number"],
    textarea {
      border: 1px solid #d1d5db;
      border-radius: 8px;
      padding: 8px 10px;
      font-size: 14px;
      background: #f9fafb;
    }

    input:focus,
    textarea:focus {
      border-color: #2563eb;
      outline: none;
      box-shadow: 0 0 0 2px rgba(37,99,235,0.1);
    }

    .materials-section {
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .material-item {
      display: flex;
      gap: 10px;
      margin-bottom: 10px;
    }

    .remove-material {
      background: #dc2626;
      color: white;
      border: none;
      border-radius: 6px;
      padding: 4px 10px;
      cursor: pointer;
    }

    .btn-secondary {
      background: #f3f4f6;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      padding: 6px 14px;
      font-weight: 600;
      cursor: pointer;
    }

    .btn-secondary:hover {
      background: #e5e7eb;
    }

    .proof-images {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 10px;
    }

    .proof-images img {
      width: 120px;
      height: 120px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid #ddd;
    }

    .delete-img {
      display: inline-block;
      color: #dc2626;
      text-decoration: none;
      font-weight: 600;
      font-size: 13px;
      margin-top: 4px;
    }

    .delete-img:hover {
      text-decoration: underline;
    }

    .form-actions {
      display: flex;
      gap: 10px;
      margin-top: 25px;
    }

    .btn-primary {
      background: #2563eb;
      color: white;
      border: none;
      border-radius: 6px;
      padding: 8px 16px;
      font-weight: 600;
      cursor: pointer;
    }

    .btn-primary:hover {
      background: #1d4ed8;
    }

    .btn-cancel {
      color: #6b7280;
      text-decoration: none;
      font-weight: 600;
    }

    .btn-cancel:hover {
      text-decoration: underline;
    }
  </style>

  <script>
    function addMaterial() {
      const container = document.getElementById("materials-container");
      const newRow = document.createElement("div");
      newRow.className = "material-item";
      newRow.innerHTML = `
        <input type="text" name="material_name[]" placeholder="Material name" required>
        <input type="text" name="material_quantity[]" placeholder="Quantity used" required>
        <button type="button" class="remove-material" onclick="this.parentElement.remove()">✕</button>
      `;
      container.appendChild(newRow);
    }

    function confirmDeleteImage(event, url) {
      event.preventDefault();
      if (confirm("Are you sure you want to delete this proof image?")) {
        window.location.href = url;
      }
    }
  </script>
</head>

<body>
  <div class="content-wrapper">
    <div class="form-card">
      <h2>Edit Report — Project: <?= htmlspecialchars($report['project_name'] ?? '') ?></h2>

      <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
          <label>Date</label>
          <input type="text" name="report_date" value="<?= htmlspecialchars($report['report_date']); ?>" required>
        </div>

        <div class="form-group">
          <label>Progress (%)</label>
          <input type="number" name="progress_percentage" value="<?= htmlspecialchars($report['progress_percentage']); ?>" min="0" max="100" required>
        </div>

        <div class="form-group">
          <label>Work Done</label>
          <textarea name="work_done" rows="3"><?= htmlspecialchars($report['work_done']); ?></textarea>
        </div>

        <div class="form-group">
          <label>Remarks</label>
          <textarea name="remarks" rows="3"><?= htmlspecialchars($report['remarks']); ?></textarea>
        </div>

        <h4>Materials Used</h4>
        <div class="materials-section" id="materials-container">
          <?php if ($materials->num_rows > 0): ?>
            <?php while ($m = $materials->fetch_assoc()): ?>
              <div class="material-item">
                <input type="text" name="material_name[]" value="<?= htmlspecialchars($m['material_name']); ?>" required>
                <input type="text" name="material_quantity[]" value="<?= htmlspecialchars($m['quantity_used']); ?>" required>
                <button type="button" class="remove-material" onclick="this.parentElement.remove()">✕</button>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="material-item">
              <input type="text" name="material_name[]" placeholder="Material name" required>
              <input type="text" name="material_quantity[]" placeholder="Quantity used" required>
              <button type="button" class="remove-material" onclick="this.parentElement.remove()">✕</button>
            </div>
          <?php endif; ?>
        </div>
        <button type="button" class="btn-secondary" onclick="addMaterial()">+ Add Material</button>

        <div class="form-group">
          <label>Proof Images (JPG/PNG/WEBP)</label>
          <div class="proof-images">
            <?php while ($img = $images->fetch_assoc()): ?>
              <div>
                <img src="<?= htmlspecialchars($img['file_path']); ?>" alt="Proof">
                <a href="#" onclick="confirmDeleteImage(event, '?id=<?= $report_id; ?>&delete_image=<?= $img['id']; ?>')" class="delete-img">Delete</a>
              </div>
            <?php endwhile; ?>
          </div>
          <input type="file" name="proof_images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
        </div>

        <div class="form-actions">
          <button type="submit" name="update_report" class="btn-primary">Update Report</button>
          <a href="../modules/view_project.php?id=<?= $project_id; ?>" class="btn-cancel">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
