<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    die('<h3 style="color:red;">Invalid project ID.</h3>');
}
$project_id = (int)$_GET['project_id'];

$units = $conn->query("SELECT id, name FROM project_units WHERE project_id = $project_id ORDER BY id ASC");
include '../includes/header.php';
?>

<div class="main-content-wrapper">
  <div class="card">
    <h2>Add Checklist Item</h2>
    <form method="POST" action="process_add_checklist_item.php">
      <input type="hidden" name="project_id" value="<?= $project_id ?>">

      <div class="form-group">
        <label for="item_description">Checklist Description:</label>
        <input type="text" name="item_description" id="item_description" required>
      </div>

      <div class="form-group">
        <label>Apply To:</label><br>
        <label><input type="radio" name="apply_mode" value="single" checked> This Unit Only</label><br>
        <label><input type="radio" name="apply_mode" value="all"> Apply to All Units</label>
      </div>

      <div class="form-group">
        <label for="unit_id">Select Unit (if not applying to all):</label>
        <select name="unit_id" id="unit_id">
          <option value="">-- General / No Unit --</option>
          <?php while ($u = $units->fetch_assoc()): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <button type="submit" class="btn">✅ Add Checklist</button>
      <a href="../modules/view_project.php?id=<?= $project_id ?>" class="btn danger">❌ Cancel</a>
    </form>
  </div>
</div>

<style>
.main-content-wrapper{max-width:600px;margin:auto;padding:20px;}
.card{background:#fff;padding:20px;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,.08);}
.form-group{margin-bottom:16px;}
label{font-weight:600;display:block;margin-bottom:6px;}
input[type=text], select{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;}
.btn{background:#2563eb;color:#fff;text-decoration:none;padding:8px 14px;border-radius:6px;}
.btn:hover{background:#1d4ed8;}
.btn.danger{background:#dc2626;}
.btn.danger:hover{background:#b91c1c;}
</style>
