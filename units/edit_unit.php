<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if (!isset($_GET['id']) || !isset($_GET['project_id'])) {
  die('<h3 style="color:red;">Invalid parameters provided.</h3>');
}

$unit_id = (int)$_GET['id'];
$project_id = (int)$_GET['project_id'];

// Fetch unit data
$unit = $conn->query("SELECT * FROM project_units WHERE id = $unit_id")->fetch_assoc();
if (!$unit) {
  die('<h3 style="color:red;">Unit not found.</h3>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = $conn->real_escape_string($_POST['name']);
  $description = $conn->real_escape_string($_POST['description']);
  $progress = (int)$_POST['progress'];

  $conn->query("UPDATE project_units 
                SET name = '$name', description = '$description', progress = $progress 
                WHERE id = $unit_id");

  header("Location: ../modules/view_project.php?id=$project_id");
  exit;
}

include '../includes/header.php';
?>

<div class="main-content-wrapper">
  <div class="card edit-unit-card">
    <h2>Edit Unit</h2>
    <form method="POST">
      <div class="form-group">
        <label>Unit Name:</label>
        <input type="text" name="name" value="<?= htmlspecialchars($unit['name']); ?>" required>
      </div>

      <div class="form-row">
        <div class="form-group half">
          <label>Progress (%):</label>
          <input type="number" name="progress" min="0" max="100" value="<?= htmlspecialchars($unit['progress']); ?>">
        </div>
        <div class="form-group half">
          <label>Description:</label>
          <input type="text" name="description" value="<?= htmlspecialchars($unit['description'] ?? ''); ?>" placeholder="Enter description...">
        </div>
      </div>

      <div class="form-actions">
        <a href="../modules/view_project.php?id=<?= $project_id; ?>" class="btn-cancel">Cancel</a>
        <button type="submit" class="btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<style>
/* 
  FIX: Add overflow: hidden to body/html to force the scrollbar to disappear. 
  This is a blunt fix for "not scrollable."
*/
html, body {
  overflow-y: hidden !important; /* Use !important to override external styles if necessary */
}

.main-content-wrapper {
  display: flex;
  justify-content: center;
  padding: 80px 20px 60px 20px; /* Use a good amount of top/bottom padding for visual center */
}
.edit-unit-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px 30px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.08);
  width: 100%;
  max-width: 700px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 16px;
}
.form-row {
  display: flex;
  justify-content: space-between;
  gap: 40px;
}
.form-group.half {
  flex: 1;
}
input[type="text"], input[type="number"] {
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 10px;
  font-size: 15px;
  width: 100%;
}
.btn-primary {
  background: #2563eb;
  color: #fff;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
}
.btn-cancel {
  background: #6b7280;
  color: #fff;
  padding: 8px 16px;
  border-radius: 6px;
  text-decoration: none;
}
.btn-primary:hover { background: #1d4ed8; }
.btn-cancel:hover { background: #4b5563; }
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 10px;
}
</style>

<?php include '../includes/footer.php'; ?>