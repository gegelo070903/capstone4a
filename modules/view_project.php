<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('<h3 style="color:red;">Invalid project ID.</h3>');
}
$project_id = intval($_GET['id']);

// Fetch project info
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) die('<h3 style="color:red;">Project not found.</h3>');

// Fetch project units
$units_stmt = $conn->prepare("SELECT * FROM project_units WHERE project_id = ?");
$units_stmt->bind_param("i", $project_id);
$units_stmt->execute();
$units_result = $units_stmt->get_result();
$units = $units_result->fetch_all(MYSQLI_ASSOC);
$units_stmt->close();

// Calculate overall progress
$total_units = count($units);
$total_progress = array_sum(array_column($units, 'progress'));
$overall_progress = $total_units > 0 ? round($total_progress / $total_units) : 0;

include '../includes/header.php';
?>

<div class="main-content-wrapper">
  <!-- HEADER -->
  <div class="project-header-card">
    <div class="header-row">
      <h2><?= htmlspecialchars($project['name']); ?></h2>
      <a href="../uploads/projects.php" class="btn-back">← Back</a>
    </div>

    <p><strong>Status:</strong> 
      <span class="status <?= strtolower($project['status']); ?>"><?= htmlspecialchars($project['status']); ?></span> |
      <strong>Location:</strong> <?= htmlspecialchars($project['location']); ?> |
      <strong>Units:</strong> <?= $project['units']; ?> |
      <strong>Created:</strong> <?= date('M d, Y', strtotime($project['created_at'])); ?>
    </p>

    <div class="progress-container">
      <div class="progress-bar" style="width: <?= $overall_progress; ?>%;"><?= $overall_progress; ?>%</div>
    </div>
  </div>

  <!-- TABS -->
  <div class="tab-container">
    <!-- FIX: Set Units tab button as active by default -->
    <button class="tab-btn active" onclick="openTab('units')">Project Units</button>
    <button class="tab-btn" onclick="openTab('materials')">Materials</button>
    <button class="tab-btn" onclick="openTab('reports')">Reports</button>
  </div>

  <!-- TAB CONTENT -->
  <!-- FIX: Set Units tab content as active by default -->
  <div id="units" class="tab-content active">
    <div class="tab-header">
      <h3>Project Units</h3>
      <button class="btn btn-primary" onclick="toggleChecklistOverlay(true)">+ Add Checklist</button>
    </div>

    <?php if (!empty($units)): ?>
      <div class="unit-grid">
        <?php foreach ($units as $unit): ?>
          <div class="unit-card">
            <h4><?= htmlspecialchars($unit['name']); ?> 
              <span class="unit-progress"><?= $unit['progress']; ?>%</span>
            </h4>
            <p><?= !empty($unit['description']) ? htmlspecialchars($unit['description']) : "No description provided."; ?></p>
            <div class="progress-container small">
              <div class="progress-bar" style="width: <?= $unit['progress']; ?>%;"></div>
            </div>
            <div class="unit-actions">
              <a href="../units/edit_unit.php?id=<?= $unit['id']; ?>&project_id=<?= $project_id; ?>" class="btn-view">Edit Unit</a>
              <a href="../checklist/view_checklist.php?unit_id=<?= $unit['id']; ?>&project_id=<?= $project_id; ?>" class="btn-view">View Checklist</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="color:#888;">No units found for this project.</p>
    <?php endif; ?>
  </div>

  <!-- ✅ MATERIALS TAB (UPDATED CONTENT) -->
  <div id="materials" class="tab-content">
    <div class="tab-header">
      <h3>Project Materials</h3>
      <button class="btn btn-primary" onclick="toggleMaterialOverlay(true)">+ Add Material</button>
    </div>

    <?php
      $materials_stmt = $conn->prepare("SELECT * FROM materials WHERE project_id = ? ORDER BY id DESC");
      $materials_stmt->bind_param("i", $project_id);
      $materials_stmt->execute();
      $materials = $materials_stmt->get_result();
    ?>

    <?php if ($materials->num_rows > 0): ?>
      <?php while ($m = $materials->fetch_assoc()): ?>
        <!-- START NEW MATERIAL CARD -->
        <div class="material-card">
          <div class="material-header-row">
            <h4><?= htmlspecialchars($m['name']); ?></h4>
            <div class="material-buttons">
              <a href="../materials/edit_material.php?id=<?= $m['id']; ?>&project_id=<?= $project_id; ?>" class="btn-primary btn-sm">Edit</a>
              <a href="../materials/delete_material.php?id=<?= $m['id']; ?>&project_id=<?= $project_id; ?>" class="btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this material?');">Delete</a>
            </div>
          </div>

          <div class="material-details">
            <p><strong>Total:</strong> <?= intval($m['total_quantity']); ?> <?= htmlspecialchars($m['unit_of_measurement']); ?></p>
            <p><strong>Remaining:</strong> <?= intval($m['remaining_quantity']); ?> <?= htmlspecialchars($m['unit_of_measurement']); ?></p>
          </div>

          <?php if (!empty($m['supplier']) || !empty($m['purpose'])): ?>
            <div class="material-meta">
              <?php if (!empty($m['supplier'])): ?>
                <p><strong>Supplier:</strong> <?= htmlspecialchars($m['supplier']); ?></p>
              <?php endif; ?>
              <?php if (!empty($m['purpose'])): ?>
                <p><strong>Purpose:</strong> <?= htmlspecialchars($m['purpose']); ?></p>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="material-date">
            <small>Added on <?= date('M d, Y', strtotime($m['created_at'])); ?></small>
          </div>
        </div>
        <!-- END NEW MATERIAL CARD -->
      <?php endwhile; ?>
    <?php else: ?>
      <p style="color:#888;">No materials recorded for this project yet.</p>
    <?php endif; ?>
  </div>

  <!-- 🎯 UPDATED REPORTS TAB (Grouped by Unit) -->
  <div id="reports" class="tab-content">
    <div class="tab-header">
      <h3>Project Reports</h3>
      <a href="../reports/add_report.php?project_id=<?= $project_id; ?>" class="btn btn-primary">+ Add Report</a>
    </div>

    <?php
    // Fetch all reports grouped by unit
    $sql = "
      SELECT pr.*, pu.name AS unit_name
      FROM project_reports pr
      LEFT JOIN project_units pu ON pr.unit_id = pu.id
      WHERE pr.project_id = ?
      ORDER BY pu.name ASC, pr.report_date DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $reports_by_unit = [];
    while ($row = $res->fetch_assoc()) {
        $unit_name = $row['unit_name'] ?: 'General / No Unit';
        $reports_by_unit[$unit_name][] = $row;
    }
    $stmt->close();
    ?>

    <?php if (empty($reports_by_unit)): ?>
      <p style="color:#888;">No reports have been added for this project yet.</p>
    <?php else: ?>
      <!-- 💥 REPORTS TAB MODIFICATION START 💥 -->
      <?php foreach ($reports_by_unit as $unit_name => $reports): ?>
        <div class="unit-report-folder">
          <h4 class="unit-folder-title"><?= htmlspecialchars($unit_name); ?></h4>

          <div class="report-scroll-container">
            <?php foreach ($reports as $r): ?>
              <div class="report-card">
                <div class="report-header">
                  <strong><?= date('F d, Y', strtotime($r['report_date'])); ?></strong>
                  <div class="report-actions">
                    <a href="../reports/view_report.php?id=<?= $r['id']; ?>" class="btn-view">View</a>
                    <a href="../reports/edit_report.php?id=<?= $r['id']; ?>" class="btn-edit">Edit</a>
                    <a href="../reports/delete_report.php?id=<?= $r['id']; ?>&project_id=<?= $project_id; ?>"
                       class="btn-delete"
                       onclick="return confirm('Are you sure you want to delete this report?');">
                       Delete
                    </a>
                  </div>
                </div>

                <p><strong>Progress:</strong> <?= (int)$r['progress_percentage']; ?>%</p>
                <p><strong>Work Done:</strong> <?= htmlspecialchars($r['work_done']); ?></p>
                <p><small><strong>Created by:</strong> <?= htmlspecialchars($r['created_by']); ?></small></p>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <!-- 💥 REPORTS TAB MODIFICATION END 💥 -->
    <?php endif; ?>
  </div>
</div>

<!-- ✅ ADD CHECKLIST OVERLAY (UNCHANGED) -->
<div class="overlay" id="checklistOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleChecklistOverlay(false)">✕</button>
    <h3 class="overlay-title">Add Checklist Item</h3>
    <form method="POST" action="../checklist/process_add_checklist_item.php" class="checklist-form">
      <input type="hidden" name="project_id" value="<?= $project_id; ?>">

      <div class="form-group">
        <label for="item_description">Checklist Description:</label>
        <input type="text" id="item_description" name="item_description" placeholder="Enter checklist description" required>
      </div>

      <div class="form-group">
        <label>Apply To:</label>
        <div class="radio-group">
          <label><input type="radio" name="apply_mode" value="single" checked> This Unit Only</label>
          <label><input type="radio" name="apply_mode" value="all"> Apply to All Units</label>
        </div>
      </div>

      <div class="form-group">
        <label for="unit_id">Select Unit (if not applying to all):</label>
        <select id="unit_id" name="unit_id">
          <option value="">-- General / No Unit --</option>
          <?php foreach ($units as $unit): ?>
            <option value="<?= $unit['id']; ?>"><?= htmlspecialchars($unit['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-actions">
        <button type="button" class="btn-cancel" onclick="toggleChecklistOverlay(false)">Cancel</button>
        <button type="submit" class="btn-primary">Add Checklist</button>
      </div>
    </form>
  </div>
</div>

<!-- ✅ UPDATED ADD MATERIAL OVERLAY (QUANTITY NAME FIXED) -->
<div class="overlay" id="materialOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleMaterialOverlay(false)">✕</button>
    <h3 class="overlay-title">Add Material</h3>

    <form id="addMaterialForm" method="POST" action="../materials/process_add_material.php">
      <input type="hidden" name="project_id" value="<?= $project_id; ?>">

      <div class="form-row">
        <div class="form-group">
          <label for="name">Material Name:</label>
          <input type="text" id="name" name="name" placeholder="e.g., Portland Cement" required>
        </div>

        <div class="form-group">
          <label for="quantity">Quantity:</label>
          <!-- FIX: Changed name="total_quantity" to name="quantity" to match backend check -->
          <input type="number" id="quantity" name="quantity" placeholder="e.g., 25" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="unit_of_measurement">Unit of Measurement:</label>
          <input type="text" id="unit_of_measurement" name="unit_of_measurement" placeholder="e.g., sacks, kg, pcs" required>
        </div>

        <div class="form-group">
          <label for="supplier">Supplier (optional):</label>
          <input type="text" id="supplier" name="supplier" placeholder="e.g., ABC Builders">
        </div>
      </div>

      <div class="form-group">
        <label for="purpose">Purpose (optional):</label>
        <input type="text" id="purpose" name="purpose" placeholder="e.g., Foundation pour">
      </div>

      <div class="form-actions">
        <button type="button" class="btn-cancel" onclick="toggleMaterialOverlay(false)">Cancel</button>
        <button type="submit" class="btn-primary">Add Material</button>
      </div>
    </form>
  </div>
</div>

<style>
/* ====== BASE STYLES (UNCHANGED) ====== */
.main-content-wrapper {
  padding: 10px;
  background: #f8f9fc;
}
.project-header-card {
  background: #fff;
  padding: 25px;
  border-radius: 10px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.08);
  margin-bottom: 25px;
}
.header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* Back Button */
.btn-back {
  background: #374151;
  color: #fff;
  text-decoration: none;
  padding: 8px 14px;
  border-radius: 8px;
  font-weight: 600;
  transition: background 0.2s ease;
  /* ENSURE BUTTON COLOR IS CORRECT */
  background-color: #374151 !important; 
  color: #fff !important;
}
.btn-back:hover {
  background: #111827;
}

/* Status Styles */
.status.pending { background: #fbbf24; color:#fff; padding:4px 10px; border-radius:6px; }
.status.ongoing { background: #2563eb; color:#fff; padding:4px 10px; border-radius:6px; }
.status.completed { background: #22c55e; color:#fff; padding:4px 10px; border-radius:6px; }

/* Progress Bar */
.progress-container {
  width: 100%;
  height: 18px;
  background: #e9ecef;
  border-radius: 10px;
  margin-top: 10px;
  overflow: hidden;
}
.progress-bar {
  height: 100%;
  text-align: center;
  color: #fff;
  background: #2563eb;
  font-size: 12px;
  line-height: 18px;
  transition: width 0.4s ease;
}

/* ====== TABS (UNCHANGED) ====== */
.tab-container {
  display: flex;
  gap: 10px;
  border-bottom: 2px solid #e5e7eb;
  margin-bottom: 15px;
}
.tab-btn {
  background: none;
  border: none;
  padding: 10px 16px;
  cursor: pointer;
  font-weight: 600;
  color: #374151;
  border-bottom: 3px solid transparent;
}
.tab-btn.active {
  border-bottom-color: #2563eb;
  color: #2563eb;
}
.tab-content {
  display: none;
  background: #fff;
  padding: 20px;
  border-radius: 10px;
  box-shadow: 0 3px 8px rgba(0,0,0,0.05);
}
.tab-content.active { display: block; }
.tab-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

/* ====== UNITS (UNCHANGED) ====== */
.unit-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}
.unit-card {
  background: #fafafa;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 20px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.04);
}
.unit-card h4 { margin: 0 0 8px; color: #111827; }
.unit-progress { float: right; color: #2563eb; font-weight: bold; }
.unit-actions { margin-top: 20px; }

/* --- Button base styles (for unit/report cards) --- */
.btn,
.btn-primary,
.btn-edit,
.btn-delete,
.btn-view,
.btn-back {
  text-decoration: none !important;
  color: inherit; 
}
.btn:hover,
.btn-primary:hover,
.btn-edit:hover,
.btn-delete:hover,
.btn-view:hover,
.btn-back:hover {
  text-decoration: none !important;
}

/* Button Colors (Ensured correct colors) */
.btn-view {
  background: #2563eb !important;
  color: #fff !important;
  padding: 8px 14px;
  border-radius: 8px;
  font-weight: 600;
  transition: background 0.2s ease;
  margin-right: 8px; 
}
.btn-edit {
  background: #2563eb !important;
  color: #fff !important;
  padding: 10px 15px;
  font-size: 13px;
  border-radius: 6px;
  font-weight: 600;
  border: none;
  cursor: pointer;
}
.btn-delete {
  background: #dc2626 !important;
  color: #fff !important;
  padding: 4px 10px;
  font-size: 13px;
  border-radius: 6px;
  font-weight: 600;
  border: none;
  cursor: pointer;
}


/* ======================================= */
/* ✅ NEW MATERIALS TAB STYLES */
/* ======================================= */

.material-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 15px 20px;
  margin-bottom: 15px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.05);
  transition: transform 0.2s ease;
}
.material-card:hover {
  transform: translateY(-2px);
}

.material-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.material-header-row h4 {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.material-buttons {
  display: flex;
  gap: 8px;
}

.btn-primary.btn-sm,
.btn-danger.btn-sm {
  padding: 6px 12px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 6px;
  text-decoration: none;
  cursor: pointer;
  display: inline-block;
}

.btn-primary.btn-sm {
  background-color: #2563eb;
  color: #fff;
}
.btn-primary.btn-sm:hover {
  background-color: #1d4ed8;
}

.btn-danger.btn-sm {
  background-color: #dc2626;
  color: #fff;
}
.btn-danger.btn-sm:hover {
  background-color: #b91c1c;
}

.material-details p,
.material-meta p {
  margin: 3px 0;
  color: #374151;
  font-size: 14px; /* Added font size for consistency */
}

.material-date small {
  color: #6b7280;
  font-size: 12px; /* Added font size for consistency */
  display: block; /* Ensure it takes its own line */
  margin-top: 10px;
}

/* Media query for smaller screens: stack header row */
@media (max-width: 550px) {
  .material-header-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
}


/* ======================================= */
/* ✅ OVERLAY & FORM STYLES */
/* ======================================= */

/* ---------- Overlay Base ---------- */
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 10000;
  padding: 16px; 
}

/* ---------- Overlay Card ---------- */
.overlay-card {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 700px;
  padding: 28px 50px 32px 50px;
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
  position: relative;
  animation: fadeIn 0.25s ease;
  max-height: 85vh; 
  overflow-y: auto; 
}

.overlay-title {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 16px;
}

/* ---------- Close Button ---------- */
.close-btn {
  position: absolute;
  top: 14px;
  right: 16px;
  background: none;
  border: none;
  font-size: 20px;
  color: #6b7280;
  cursor: pointer;
}
.close-btn:hover { color: #111827; }

/* ---------- Form Layout ---------- */

/* Add extra breathing room between major rows (UPDATED) */
.checklist-form,
#addMaterialForm { 
  display: flex;
  flex-direction: column;
  gap: 16px; /* vertical spacing between grouped rows */
}

/* Spacing between rows of input groups (UPDATED) */
.form-row {
  display: flex;
  gap: 45px; /* adds space between left and right columns (UPDATED) */
  margin-bottom: 14px; /* adds space below each pair */
}

/* Space below each full-width input row (UPDATED) */
.form-group {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px; /* space between label and input */
}

label {
  font-weight: 600;
  color: #374151;
  font-size: 14px;
}

input[type="text"],
input[type="number"],
input[type="date"],
input[type="time"],
select {
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 15px;
  width: 100%;
  background: #fff;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  margin-bottom: 6px; /* Optional: make fields look slightly more separated visually (ADDED) */
}

input[type="text"]:focus,
input[type="number"]:focus,
input[type="date"]:focus,
input[type="time"]:focus,
select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.2);
}

/* Specific to Checklist Overlay */
.radio-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-left: 4px;
}

/* ---------- Buttons ---------- */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 15px; 
}

/* FIX: Larger size for main form actions (like Cancel/Submit in overlays) */
.btn-primary {
  background: #2563eb;
  color: #fff;
  padding: 9px 16px; /* Larger size */
  border: none;
  border-radius: 8px; /* Larger radius */
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease;
  font-size: 12px; /* NEW: Reduced font size for the button text */
}
.btn-primary:hover { background: #1d4ed8; }

/* NEW FIX: Specific smaller size for the tab-header buttons */
#reports .btn-primary {
  padding: 6px 10px; /* Smaller padding for Add Report link only */
  border-radius: 8px;
  font-weight: 700;
}

/* NEW FIX: Targeting the tab-header buttons to match the desired size/weight */
.tab-header .btn-primary {
  padding: 6px 10px; /* Same small padding as Add Report */
  border-radius: 8px; 
  font-weight: 700;
}


.btn-cancel {
  background: #6b7280;
  color: #fff;
  padding: 9px 16px; 
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease;
}
.btn-cancel:hover { background: #4b5563; }

/* ---------- Animation ---------- */
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.96); }
  to { opacity: 1; transform: scale(1); }
}

/* ---------- Responsive (From new material CSS) ---------- */
@media (max-width: 700px) {
  .overlay-card {
    max-width: 90%;
    padding: 22px;
  }
  .form-row {
    flex-direction: column;
    margin-bottom: 0; /* Remove extra margin on small screens when stacked */
  }
  .form-row .form-group {
    margin-bottom: 14px; /* Apply row spacing to the groups themselves when stacked */
  }
}


/* ======================================= */
/* 🎯 REPORTS TAB (GROUPED BY UNIT) STYLES */
/* ======================================= */

.unit-report-folder {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 16px;
  margin-bottom: 20px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.unit-folder-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 12px;
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 4px;
}

.report-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 14px;
  /* Removed margin-bottom here as it's handled by .report-scroll-container gap */
  box-shadow: 0 1px 2px rgba(0,0,0,0.04);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.report-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 3px 8px rgba(0,0,0,0.08);
}

.report-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.report-actions {
  display: flex;
  gap: 6px;
}

.btn-view,
.btn-edit,
.btn-delete {
  padding: 5px 10px;
  font-size: 13px;
  border-radius: 6px;
  font-weight: 600;
  text-decoration: none;
  color: #fff;
}

/* Overriding original button styles to use new report-specific ones */
.report-actions .btn-view { background: #2563eb; }
.report-actions .btn-edit { background: #6b7280; }
.report-actions .btn-delete { background: #dc2626; }

.report-actions .btn-view:hover { background: #1d4ed8; }
.report-actions .btn-edit:hover { background: #4b5563; }
.report-actions .btn-delete:hover { background: #b91c1c; }

@media (max-width: 600px) {
  .report-header { flex-direction: column; align-items: flex-start; gap: 6px; }
  .report-actions { flex-wrap: wrap; gap: 4px; }
}
/* END REPORTS TAB STYLES */

/* 🎯 Scrollable Report Container */
.report-scroll-container {
  max-height: 350px;           /* control the visible height */
  overflow-y: auto;            /* scroll only vertically */
  padding-right: 8px;          /* space for scrollbar */
  display: flex;
  flex-direction: column;
  gap: 10px;                   /* space between report cards */
}

/* Optional — prettier scrollbars */
.report-scroll-container::-webkit-scrollbar {
  width: 6px;
}
.report-scroll-container::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.report-scroll-container::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

/* --- NOTE: Removed duplicate/old report styles from previous versions --- */

</style>

<script>
function openTab(tabId) {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.remove('active');
    // Ensure the tab button corresponding to the tabId is marked active
    if (btn.getAttribute('onclick').includes(`'${tabId}'`)) {
        btn.classList.add('active');
    }
  });
  document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
  document.getElementById(tabId).classList.add('active');
}

function toggleChecklistOverlay(show) {
  document.getElementById('checklistOverlay').style.display = show ? 'flex' : 'none';
}

function toggleMaterialOverlay(show) {
  document.getElementById('materialOverlay').style.display = show ? 'flex' : 'none';
}

// FIX IMPLEMENTED: Read the 'tab' query parameter and open the correct tab
document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const tab = params.get('tab') || 'units';
  openTab(tab);
});
</script>

<?php include '../includes/footer.php'; ?>