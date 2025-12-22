<?php
// modules/view_project.php

// =======================================================================
// ✅ ERROR CHECKER: FORCES DISPLAY OF ALL ERRORS AND WARNINGS
// =======================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start a global try block to catch fatal errors that aren't database related
try {

// =======================================================================
// PHP START (CORE LOGIC)
// =======================================================================

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

// Fetch project units WITH calculated progress from checklist items
$units_stmt = $conn->prepare("
    SELECT pu.id, pu.name, pu.description, pu.progress,
           (SELECT COUNT(*) FROM project_checklists pc WHERE pc.unit_id = pu.id) as total_items,
           (SELECT COUNT(*) FROM project_checklists pc WHERE pc.unit_id = pu.id AND pc.is_completed = 1) as completed_items
    FROM project_units pu 
    WHERE pu.project_id = ?
"); 
$units_stmt->bind_param("i", $project_id);
$units_stmt->execute();
$units_result = $units_stmt->get_result();
$units = [];
while ($row = $units_result->fetch_assoc()) {
    // Calculate progress based on checklist completion
    $total = (int)$row['total_items'];
    $completed = (int)$row['completed_items'];
    $row['calculated_progress'] = $total > 0 ? round(($completed / $total) * 100) : 0;
    // Use calculated progress instead of stored progress
    $row['progress'] = $row['calculated_progress'];
    $units[] = $row;
}
$units_stmt->close();

// Calculate overall progress
$total_units = count($units);
$total_progress = array_sum(array_column($units, 'progress'));
$overall_progress = $total_units > 0 ? round($total_progress / $total_units) : 0;

// =======================================================================
// ✅ AUTOMATIC STATUS CHANGE LOGIC
// =======================================================================

if ($project['status'] !== 'Completed' && $overall_progress >= 100) {
    // Progress reached 100% - mark as Completed
    $update_stmt = $conn->prepare("UPDATE projects SET status = 'Completed' WHERE id = ?");
    $update_stmt->bind_param("i", $project_id);
    
    if ($update_stmt->execute()) {
        $project['status'] = 'Completed'; 
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('Project automatically set to Completed! Overall progress reached 100%.', 'success'); });</script>";
    }
    $update_stmt->close();
} elseif ($project['status'] === 'Completed' && $overall_progress < 100) {
    // Progress dropped below 100% - revert to Ongoing
    $update_stmt = $conn->prepare("UPDATE projects SET status = 'Ongoing' WHERE id = ?");
    $update_stmt->bind_param("i", $project_id);
    
    if ($update_stmt->execute()) {
        $project['status'] = 'Ongoing'; 
    }
    $update_stmt->close();
}

// Update the project progress in the database (for display in projects.php)
if ($project['progress'] != $overall_progress) {
    $progress_update_stmt = $conn->prepare("UPDATE projects SET progress = ? WHERE id = ?");
    $progress_update_stmt->bind_param("ii", $overall_progress, $project_id);
    $progress_update_stmt->execute();
    $progress_update_stmt->close();
    $project['progress'] = $overall_progress;
}

// =======================================================================
// END NEW LOGIC
// =======================================================================

// =======================================================================
// ✅ ROBUST PHP DATA FETCHING FOR EMBEDDED ADD REPORT FORM
// =======================================================================

// 1. Fetch materials for dropdown
$mats = [];
$ms = $conn->prepare("SELECT id, name, remaining_quantity, unit_of_measurement FROM materials WHERE project_id = ? ORDER BY name");

if ($ms) {
    $ms->bind_param('i', $project_id);
    $ms->execute();
    $matsRes = $ms->get_result();
    while ($r = $matsRes->fetch_assoc()) {
        $r['remaining_quantity'] = (int)$r['remaining_quantity'];
        $mats[] = $r;
    }
    $ms->close();
} else {
    // This alert should disappear once the database is fixed.
    echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('FATAL ERROR: Materials query preparation failed. Check your materials table and connection.', 'error'); });</script>";
}


// 2. Fetch COMPLETED checklist items with images for the "Work Done" dropdown
// Now using the new checklist_images table for multiple images support
$checklist_items = [];
$sql_checklist = "
    SELECT pc.id, pc.item_description, pc.unit_id, pu.name AS unit_name,
           (SELECT COUNT(*) FROM checklist_images ci WHERE ci.checklist_id = pc.id) as image_count
    FROM project_checklists pc
    JOIN project_units pu ON pc.unit_id = pu.id
    WHERE pc.project_id = ? AND pc.is_completed = 1
    AND EXISTS (SELECT 1 FROM checklist_images ci WHERE ci.checklist_id = pc.id)
    ORDER BY pu.name ASC, pc.item_description ASC
";
$check_stmt = $conn->prepare($sql_checklist);

if ($check_stmt) {
    $check_stmt->bind_param('i', $project_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    while ($row = $check_result->fetch_assoc()) {
        // Fetch all images for this checklist item
        $img_stmt = $conn->prepare("SELECT id, image_path FROM checklist_images WHERE checklist_id = ?");
        $img_stmt->bind_param('i', $row['id']);
        $img_stmt->execute();
        $img_result = $img_stmt->get_result();
        $images = [];
        while ($img = $img_result->fetch_assoc()) {
            $images[] = $img['image_path'];
        }
        $img_stmt->close();
        $row['images'] = $images;
        $checklist_items[] = $row;
    }
    $check_stmt->close();
} else {
    echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('FATAL ERROR: Checklist query preparation failed.', 'error'); });</script>";
}

// 3. Determine initial form values (prefill)
$selected_unit_id = (int)($_GET['unit_id'] ?? 0);
$prefill_progress = 0;
$report_date_value = date('m-d-Y');

if ($selected_unit_id > 0) {
    // Look up the progress for the pre-selected unit (if any) from the $units array
    foreach ($units as $u) {
        if ((int)$u['id'] === $selected_unit_id) {
            $prefill_progress = (int)$u['progress'];
            break;
        }
    }
}

$progress_value = $prefill_progress; 

// =======================================================================
// END EMBEDDED FORM DATA FETCHING
// =======================================================================

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
      <strong>Project/Units:</strong> <?= $project['units']; ?> |
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
              <button type="button" class="btn-view" onclick="openEditUnitOverlay(<?= $unit['id']; ?>, '<?= htmlspecialchars(addslashes($unit['name']), ENT_QUOTES); ?>', '<?= htmlspecialchars(addslashes($unit['description'] ?? ''), ENT_QUOTES); ?>')">Edit Unit</button>
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
              <button type="button" class="btn-primary btn-sm" onclick="openEditMaterialOverlay(<?= $m['id']; ?>, '<?= htmlspecialchars(addslashes($m['name']), ENT_QUOTES); ?>', <?= intval($m['total_quantity']); ?>, '<?= htmlspecialchars(addslashes($m['unit_of_measurement']), ENT_QUOTES); ?>', '<?= htmlspecialchars(addslashes($m['supplier'] ?? ''), ENT_QUOTES); ?>', '<?= htmlspecialchars(addslashes($m['purpose'] ?? ''), ENT_QUOTES); ?>')">Edit</button>
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
      <!-- ✅ MODIFIED: Button calls JS function to show embedded overlay -->
      <button class="btn btn-primary" onclick="toggleReportOverlay(true)">+ Add Report</button>
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
    
    // NOTE: The function get_materials_used_for_report($conn, $report_id) is 
    // now correctly available globally via includes/functions.php
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
              <?php
                // ✅ FIX: Defensive Date Check Logic
                $db_date = $r['report_date'];
                $display_date = 'Date Not Set'; // Default placeholder

                if ($db_date && $db_date !== '0000-00-00') {
                    $timestamp = strtotime($db_date);
                    if ($timestamp !== false && $timestamp > 0) {
                        $display_date = date('F d, Y', $timestamp);
                    }
                }
              ?>
              <div class="report-card">
                <div class="report-header">
                  <!-- ✅ FIX: Use the safely formatted date -->
                  <strong><?= htmlspecialchars($display_date); ?></strong>
                  <div class="report-actions">
                    <a href="../reports/view_report.php?id=<?= $r['id']; ?>" class="btn-view">View</a>
                    <button type="button" class="btn-edit" onclick="openEditReportOverlay(<?= $r['id']; ?>)">Edit</button>
                    <a href="../reports/delete_report.php?id=<?= $r['id']; ?>&project_id=<?= $project_id; ?>"
                       class="btn-delete"
                       onclick="return confirm('Are you sure you want to delete this report?');">
                       Delete
                    </a>
                  </div>
                </div>

                <p><strong>Progress:</strong> <?= (int)$r['progress_percentage']; ?>%</p>
                <p><strong>Work Done:</strong> <?= htmlspecialchars($r['work_done']); ?></p>
                
                <!-- ✅ Materials Used Display -->
                <?php
                // Call the function from the included functions.php
                $materials_used = get_materials_used_for_report($conn, (int)$r['id']); 
                if (!empty($materials_used)) {
                    echo '<p style="margin-top:10px;"><strong>Materials Used:</strong></p>';
                    echo '<table>';
                    echo '<thead><tr>
                            <th>Material</th>
                            <th style="text-align:center;">Quantity</th>
                            <th style="text-align:center;">Unit</th>
                          </tr></thead>';

                    foreach ($materials_used as $m) {
                        echo '<tr>
                                <td>' . htmlspecialchars($m['material_name']) . '</td>
                                <td style="text-align:center;">' . (int)$m['quantity_used'] . '</td>
                                <td style="text-align:center;">' . htmlspecialchars($m['unit']) . '</td>
                              </tr>';
                    }

                    echo '</table>';
                }
                ?>
                <!-- END NEW MATERIALS DISPLAY -->

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

<!-- ✅ ADD CHECKLIST OVERLAY -->
<div class="overlay" id="checklistOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleChecklistOverlay(false)">✕</button>
    <h3 class="overlay-title">Add Checklist Item</h3>
    <form id="addChecklistForm" method="POST" action="../checklist/process_add_checklist_item.php" class="checklist-form">
      <input type="hidden" name="project_id" value="<?= $project_id; ?>">

      <div class="form-group">
        <label for="item_description">Checklist Description:</label>
        <input type="text" id="item_description" name="item_description" placeholder="Enter checklist description" required>
      </div>

      <div class="form-group">
        <label for="checklist_unit_id">Select Unit:</label>
        <select id="checklist_unit_id" name="unit_id" required>
          <option value="">-- Select a Unit --</option>
          <option value="all">Apply to All Units</option>
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

<!-- ✅ EDIT UNIT OVERLAY -->
<div class="overlay" id="editUnitOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleEditUnitOverlay(false)">✕</button>
    <h3 class="overlay-title">Edit Unit</h3>
    <form id="editUnitForm" method="POST" action="../units/edit_unit.php">
      <input type="hidden" name="unit_id" id="edit_unit_id">
      <input type="hidden" name="project_id" value="<?= $project_id; ?>">

      <div class="form-group">
        <label for="edit_unit_name">Unit Name:</label>
        <input type="text" id="edit_unit_name" name="name" placeholder="Enter unit name" required>
      </div>

      <div class="form-group">
        <label for="edit_unit_description">Description:</label>
        <input type="text" id="edit_unit_description" name="description" placeholder="Enter description (optional)">
      </div>

      <div class="form-actions">
        <button type="button" class="btn-cancel" onclick="toggleEditUnitOverlay(false)">Cancel</button>
        <button type="submit" class="btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ✅ EDIT MATERIAL OVERLAY -->
<div class="overlay" id="editMaterialOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleEditMaterialOverlay(false)">✕</button>
    <h3 class="overlay-title">Edit Material</h3>

    <form id="editMaterialForm" method="POST" action="../materials/edit_material.php">
      <input type="hidden" name="material_id" id="edit_material_id">
      <input type="hidden" name="project_id" value="<?= $project_id; ?>">

      <div class="form-row">
        <div class="form-group">
          <label for="edit_material_name">Material Name:</label>
          <input type="text" id="edit_material_name" name="name" required>
        </div>

        <div class="form-group">
          <label for="edit_total_quantity">Total Quantity:</label>
          <input type="number" id="edit_total_quantity" name="total_quantity" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="edit_unit_of_measurement">Unit of Measurement:</label>
          <input type="text" id="edit_unit_of_measurement" name="unit_of_measurement" required>
        </div>

        <div class="form-group">
          <label for="edit_supplier">Supplier (optional):</label>
          <input type="text" id="edit_supplier" name="supplier">
        </div>
      </div>

      <div class="form-group">
        <label for="edit_purpose">Purpose (optional):</label>
        <input type="text" id="edit_purpose" name="purpose">
      </div>

      <div class="form-actions">
        <button type="button" class="btn-cancel" onclick="toggleEditMaterialOverlay(false)">Cancel</button>
        <button type="submit" class="btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================= -->
<!-- ✅ NEW: EMBEDDED ADD REPORT OVERLAY (HTML) -->
<!-- ======================================================= -->
<div class="overlay" id="addReportOverlay">
  <div class="overlay-card" style="max-width: 700px;">
    <button class="close-btn" onclick="toggleReportOverlay(false)">✕</button>
    <h3 class="overlay-title">Add Report — Project: <?= htmlspecialchars($project['name']); ?></h3>

    <!-- Error Box Placeholder -->
    <div class="error-box" id="report-error-box" style="display:none;">
        <ul id="report-error-list" style="margin:0 0 0 18px;"></ul>
    </div>
    
    <!-- IMPORTANT: action points to the processing script and uses multipart/form-data -->
    <form id="addReportForm" method="post" action="../reports/add_report.php" enctype="multipart/form-data">
        <input type="hidden" name="ajax_request" value="1">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="form-grid">
            <!-- 1. Unit Selection -->
            <div class="form-group">
              <label for="report_unit_id">Select Unit</label>
              <select id="report_unit_id" name="unit_id" required>
                <option value="">— Select Unit —</option>
                <?php foreach ($units as $u): ?>
                  <option value="<?= (int)$u['id'] ?>" data-progress="<?= (int)$u['progress'] ?>" <?= $selected_unit_id == (int)$u['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- 2. Date -->
            <div class="form-group">
              <label for="report_date">Date (MM-DD-YYYY)</label>
              <input type="text" id="report_date" name="report_date" value="<?= $report_date_value; ?>" required>
            </div>
            
            <!-- 3. Progress -->
            <div class="form-group">
              <label for="progress_percentage">Progress (%)</label>
              <input type="number" id="progress_percentage" name="progress_percentage"
                     value="<?= $progress_value ?>" min="0" max="100" required
                     style="background-color:#e5e7eb; cursor:not-allowed;" readonly>
            </div>
        </div>
        
        <div class="section-divider"></div>

        <!-- 4. Work Done (Checklist Dropdown) -->
        <div class="form-group">
          <label for="work_done_checklist">Select Work Done (from Completed Checklist)</label>
          <select id="work_done_checklist" name="work_done_checklist" required>
            <option value="">— Select Completed Checklist Item —</option>
            <?php foreach ($checklist_items as $item): 
                $option_label = htmlspecialchars($item['unit_name'] . ' - ' . $item['item_description']);
                $images_json = htmlspecialchars(json_encode($item['images'] ?? []));
            ?>
              <option value="<?= $item['id'] ?>" data-images="<?= $images_json ?>" data-unit-id="<?= (int)$item['unit_id'] ?>">
                <?= $option_label ?>
              </option>
            <?php endforeach; ?>
          </select>
          <!-- Hidden field to hold the full work done description for the report table -->
          <input type="hidden" name="work_done" id="work_done_hidden"> 
        </div>

        <!-- 5. Remarks (Textarea kept for additional notes) -->
        <div class="form-group">
          <label for="remarks">Remarks (Optional)</label>
          <textarea id="remarks" name="remarks" rows="3"></textarea>
        </div>

        <div class="section-divider"></div>

        <!-- 6. Materials Used -->
        <h4>Materials Used</h4>
        <div id="materials-rows"></div> 
        <button type="button" class="btn-secondary" id="add-material-row">+ Add Material</button>

        <div class="section-divider"></div>
        
        <!-- 7. Proof Images (Populated by JS from selected checklist item) -->
        <h4>Proof Images (from Checklist)</h4>
        <div id="proof-images-container">
            <p id="image-placeholder" style="color:#6b7280;">Select a checklist item above to load its proof images.</p>
        </div>
        
        <!-- Hidden input to hold image paths -->
        <input type="hidden" name="proof_images_from_checklist" id="proof_images_from_checklist">


        <div class="form-actions">
          <button type="submit" name="save_report" class="btn-primary">Save Report</button>
          <button type="button" class="btn-cancel" onclick="toggleReportOverlay(false)">Cancel</button>
        </div>
    </form>
  </div>
</div>
<!-- END ADD REPORT OVERLAY -->

<!-- ======================================================= -->
<!-- ✅ EDIT REPORT OVERLAY -->
<!-- ======================================================= -->
<div class="overlay" id="editReportOverlay">
  <div class="overlay-card" style="max-width: 700px;">
    <button class="close-btn" onclick="toggleEditReportOverlay(false)">✕</button>
    <h3 class="overlay-title">Edit Report — Project: <?= htmlspecialchars($project['name']); ?></h3>

    <!-- Error Box Placeholder -->
    <div class="error-box" id="edit-report-error-box" style="display:none;">
        <ul id="edit-report-error-list" style="margin:0 0 0 18px;"></ul>
    </div>
    
    <form id="editReportForm" method="post" action="../reports/edit_report.php" enctype="multipart/form-data">
        <input type="hidden" name="report_id" id="edit_report_id">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">

        <div class="form-grid">
            <!-- 1. Unit Selection (readonly for edit) -->
            <div class="form-group">
              <label for="edit_report_unit_id">Unit</label>
              <select id="edit_report_unit_id" name="unit_id" disabled style="background-color:#e5e7eb;">
                <?php foreach ($units as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="unit_id" id="edit_report_unit_id_hidden">
            </div>
            <!-- 2. Date -->
            <div class="form-group">
              <label for="edit_report_date">Date (MM-DD-YYYY)</label>
              <input type="text" id="edit_report_date" name="report_date" required>
            </div>
            
            <!-- 3. Progress -->
            <div class="form-group">
              <label for="edit_progress_percentage">Progress (%) - Based on Checklist</label>
              <input type="number" id="edit_progress_percentage" name="progress_percentage"
                     min="0" max="100" required readonly style="background-color:#e5e7eb; cursor:not-allowed;">
            </div>
        </div>
        
        <div class="section-divider"></div>

        <!-- 4. Work Done -->
        <div class="form-group">
          <label for="edit_work_done">Work Done</label>
          <input type="text" id="edit_work_done" name="work_done" required>
        </div>

        <!-- 5. Remarks -->
        <div class="form-group">
          <label for="edit_remarks">Remarks (Optional)</label>
          <textarea id="edit_remarks" name="remarks" rows="3"></textarea>
        </div>

        <div class="section-divider"></div>

        <!-- 6. Existing Materials Used (display only) -->
        <h4>Current Materials Used</h4>
        <div id="edit-existing-materials" style="margin-bottom:15px; color:#6b7280;">
            <p>Loading...</p>
        </div>

        <!-- 7. Add New Materials -->
        <h4>Add More Materials</h4>
        <div id="edit-materials-rows"></div> 
        <button type="button" class="btn-secondary" id="edit-add-material-row">+ Add Material</button>

        <div class="section-divider"></div>
        
        <!-- 8. Existing Images -->
        <h4>Current Proof Images</h4>
        <div id="edit-existing-images" style="margin-bottom:15px;">
            <p style="color:#6b7280;">Loading...</p>
        </div>

        <!-- 9. Upload New Images -->
        <div class="form-group">
          <label for="edit_proof_images">Upload Additional Images (Optional)</label>
          <input type="file" id="edit_proof_images" name="proof_images[]" multiple accept="image/*">
        </div>

        <div class="form-actions">
          <button type="submit" name="update_report" class="btn-primary">Update Report</button>
          <button type="button" class="btn-cancel" onclick="toggleEditReportOverlay(false)">Cancel</button>
        </div>
    </form>
  </div>
</div>
<!-- END EDIT REPORT OVERLAY -->

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
  border: none;
  cursor: pointer;
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
  border-bottom: 1px solid #e5e7eb; /* Added for the report modal header */
  padding-bottom: 10px; /* Added for the report modal header */
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

/* --- NEW CSS for Materials Used Table (Instruction 3) --- */
table {
  border-collapse: collapse;
  width: 100%;
  margin: 8px 0;
}
.report-card th, .report-card td {
  border: 1px solid #ccc;
  padding: 6px 8px;
  text-align: left;
  font-size: 13px;
}
.report-card th {
  background: #f2f2f2;
  font-weight: 600;
}

/* === Embedded Form Specific Styles === */

/* Error Box Style */
.error-box {
  background:#f8d7da; 
  color:#721c24; 
  padding:10px; 
  border:1px solid #f5c6cb; 
  border-radius: 6px;
  margin-bottom: 15px;
}

/* Form Layouts */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}
.form-group {
    display: flex;
    flex-direction: column;
    margin-bottom: 0; 
}
.form-group.full-width {
    grid-column: 1 / -1;
}

/* Progress Range Slider Styling */
input[type="range"] {
    -webkit-appearance: none;
    width: 100%;
    height: 10px;
    background: #d1d5db;
    border-radius: 5px;
    outline: none;
    margin-top: 10px;
}

input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #2563eb;
    cursor: pointer;
}
.btn-secondary {
  background-color: #f3f4f6;
  color: #111827;
  font-weight: 600;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 6px 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-secondary:hover {
  background-color: #e5e7eb;
}
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
  if (!show) {
    document.getElementById('addChecklistForm').reset();
  }
}

function toggleMaterialOverlay(show) {
  document.getElementById('materialOverlay').style.display = show ? 'flex' : 'none';
  if (!show) {
    document.getElementById('addMaterialForm').reset();
  }
}

// ✅ Edit Unit Overlay Functions
function toggleEditUnitOverlay(show) {
  document.getElementById('editUnitOverlay').style.display = show ? 'flex' : 'none';
}

function openEditUnitOverlay(unitId, unitName, unitDescription) {
  document.getElementById('edit_unit_id').value = unitId;
  document.getElementById('edit_unit_name').value = unitName;
  document.getElementById('edit_unit_description').value = unitDescription || '';
  toggleEditUnitOverlay(true);
}

// ✅ Edit Material Overlay Functions
function toggleEditMaterialOverlay(show) {
  document.getElementById('editMaterialOverlay').style.display = show ? 'flex' : 'none';
}

function openEditMaterialOverlay(materialId, name, totalQuantity, unit, supplier, purpose) {
  document.getElementById('edit_material_id').value = materialId;
  document.getElementById('edit_material_name').value = name;
  document.getElementById('edit_total_quantity').value = totalQuantity;
  document.getElementById('edit_unit_of_measurement').value = unit;
  document.getElementById('edit_supplier').value = supplier || '';
  document.getElementById('edit_purpose').value = purpose || '';
  toggleEditMaterialOverlay(true);
}

// ✅ Edit Report Overlay Functions
function toggleEditReportOverlay(show) {
  document.getElementById('editReportOverlay').style.display = show ? 'flex' : 'none';
  if (!show) {
    document.getElementById('editReportForm').reset();
    document.getElementById('edit-existing-materials').innerHTML = '<p>Loading...</p>';
    document.getElementById('edit-existing-images').innerHTML = '<p style="color:#6b7280;">Loading...</p>';
    document.getElementById('edit-materials-rows').innerHTML = '';
  }
}

function openEditReportOverlay(reportId) {
  // Show loading state
  toggleEditReportOverlay(true);
  
  fetch(`../reports/get_report.php?id=${reportId}`)
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        showToast(data.message || 'Failed to load report data', 'error');
        toggleEditReportOverlay(false);
        return;
      }
      
      const r = data.report;
      
      // Populate form fields
      document.getElementById('edit_report_id').value = r.id;
      document.getElementById('edit_report_unit_id').value = r.unit_id;
      document.getElementById('edit_report_unit_id_hidden').value = r.unit_id;
      document.getElementById('edit_report_date').value = r.report_date;
      document.getElementById('edit_progress_percentage').value = r.progress_percentage;
      document.getElementById('edit_work_done').value = r.work_done;
      document.getElementById('edit_remarks').value = r.remarks || '';
      
      // Display existing materials
      const matsContainer = document.getElementById('edit-existing-materials');
      if (r.materials_used && r.materials_used.length > 0) {
        let matsHtml = '<table style="width:100%; font-size:13px;"><thead><tr><th>Material</th><th style="text-align:center;">Qty Used</th><th style="text-align:center;">Unit</th></tr></thead><tbody>';
        r.materials_used.forEach(m => {
          matsHtml += `<tr><td>${m.name}</td><td style="text-align:center;">${m.quantity_used}</td><td style="text-align:center;">${m.unit_of_measurement}</td></tr>`;
        });
        matsHtml += '</tbody></table>';
        matsContainer.innerHTML = matsHtml;
      } else {
        matsContainer.innerHTML = '<p style="color:#6b7280;">No materials recorded for this report.</p>';
      }
      
      // Display existing images
      const imgsContainer = document.getElementById('edit-existing-images');
      if (r.images && r.images.length > 0) {
        let imgsHtml = '<div style="display:flex; flex-wrap:wrap; gap:10px;">';
        r.images.forEach(img => {
          imgsHtml += `<img src="../reports/report_images/${img.image_path}" style="width:80px; height:80px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb;">`;
        });
        imgsHtml += '</div>';
        imgsContainer.innerHTML = imgsHtml;
      } else {
        imgsContainer.innerHTML = '<p style="color:#6b7280;">No images attached to this report.</p>';
      }
    })
    .catch(err => {
      console.error(err);
      showToast('Error loading report data.', 'error');
      toggleEditReportOverlay(false);
    });
}

// ✅ NEW: Report Overlay Toggle
function toggleReportOverlay(show) {
    document.getElementById('addReportOverlay').style.display = show ? 'flex' : 'none';
}

// Helper function to reload page with specific tab
function reloadWithTab(tabName) {
  const url = new URL(window.location.href);
  url.searchParams.set('tab', tabName);
  // Remove status/message params to avoid showing toast again
  url.searchParams.delete('status');
  url.searchParams.delete('message');
  window.location.href = url.toString();
}

// FIX IMPLEMENTED: Read the 'tab' query parameter and open the correct tab
document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const tab = params.get('tab') || 'units';
  openTab(tab);

  // ✅ AJAX: Add Checklist Form Handler
  const addChecklistForm = document.getElementById('addChecklistForm');
  if (addChecklistForm) {
    addChecklistForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      
      fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast(data.message, 'success');
          toggleChecklistOverlay(false);
          setTimeout(() => reloadWithTab('units'), 1000);
        } else {
          showToast(data.message, 'error');
        }
      })
      .catch(err => {
        console.error(err);
        showToast('Error adding checklist item. Please try again.', 'error');
      });
    });
  }

  // ✅ AJAX: Add Material Form Handler
  const addMaterialForm = document.getElementById('addMaterialForm');
  if (addMaterialForm) {
    addMaterialForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      
      fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast(data.message, 'success');
          toggleMaterialOverlay(false);
          setTimeout(() => reloadWithTab('materials'), 1000);
        } else {
          showToast(data.message, 'error');
        }
      })
      .catch(err => {
        console.error(err);
        showToast('Error adding material. Please try again.', 'error');
      });
    });
  }

  // ✅ AJAX: Edit Unit Form Handler
  const editUnitForm = document.getElementById('editUnitForm');
  if (editUnitForm) {
    editUnitForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      
      fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast(data.message, 'success');
          toggleEditUnitOverlay(false);
          setTimeout(() => reloadWithTab('units'), 1000);
        } else {
          showToast(data.message, 'error');
        }
      })
      .catch(err => {
        console.error(err);
        showToast('Error updating unit. Please try again.', 'error');
      });
    });
  }

  // ✅ AJAX: Edit Material Form Handler
  const editMaterialForm = document.getElementById('editMaterialForm');
  if (editMaterialForm) {
    editMaterialForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      
      fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast(data.message, 'success');
          toggleEditMaterialOverlay(false);
          setTimeout(() => reloadWithTab('materials'), 1000);
        } else {
          showToast(data.message, 'error');
        }
      })
      .catch(err => {
        console.error(err);
        showToast('Error updating material. Please try again.', 'error');
      });
    });
  }

  // ✅ AJAX: Edit Report Form Handler
  const editReportForm = document.getElementById('editReportForm');
  if (editReportForm) {
    // Add material row functionality for edit report
    const editMaterialsRows = document.getElementById('edit-materials-rows');
    const editAddMatBtn = document.getElementById('edit-add-material-row');
    
    function editRowTemplate() {
      let opts = MATS_DATA.map(m => `
        <option value="${m.id}" data-rem="${m.remaining_quantity}" data-uom="${m.unit_of_measurement}">
          ${m.name} (rem: ${Math.floor(m.remaining_quantity)} ${m.unit_of_measurement})
        </option>`).join('');

      return `
        <div class="mat-row" style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
          <select name="new_material_id[]" class="mat-select" required style="flex:2;">
            <option value="">Select material</option>
            ${opts}
          </select>
          <input type="number" name="new_quantity_used[]" min="1" placeholder="Qty Used" required style="flex:1;">
          <span class="unit-label" style="min-width:40px; color:#555;"></span>
          <button type="button" class="remove-row btn-secondary" style="padding: 6px 10px;">Remove</button>
        </div>`;
    }

    editAddMatBtn.addEventListener('click', () => {
      editMaterialsRows.insertAdjacentHTML('beforeend', editRowTemplate());
    });

    editMaterialsRows.addEventListener('click', (e) => {
      if (e.target.classList.contains('remove-row')) {
        e.target.closest('.mat-row').remove();
      }
    });

    editMaterialsRows.addEventListener('change', (e) => {
      if (e.target.classList.contains('mat-select')) {
        const selected = e.target.options[e.target.selectedIndex];
        const uom = selected.dataset.uom || '';
        const unitLabel = e.target.closest('.mat-row').querySelector('.unit-label');
        unitLabel.textContent = uom ? `(${uom})` : '';
      }
    });

    editReportForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      const errorBox = document.getElementById('edit-report-error-box');
      const errorList = document.getElementById('edit-report-error-list');
      
      errorBox.style.display = 'none';
      errorList.innerHTML = '';
      
      fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast(data.message, 'success');
          toggleEditReportOverlay(false);
          setTimeout(() => reloadWithTab('reports'), 1000);
        } else {
          errorList.innerHTML = `<li>${data.message}</li>`;
          errorBox.style.display = 'block';
        }
      })
      .catch(err => {
        console.error(err);
        showToast('Error updating report. Please try again.', 'error');
      });
    });
  }

  // --- START NEW REPORT FORM SETUP (Embedded Logic) ---
  // ✅ Materials data available for both Add and Edit Report forms
  const MATS_DATA = <?= json_encode($mats) ?>; 
  const BASE_IMAGE_URL = '../uploads/checklist_proofs/';

  const reportOverlayForm = document.getElementById('addReportForm');
  if (reportOverlayForm) {
      const materialsRows = document.getElementById('materials-rows'); 
      const addMatBtn = document.getElementById('add-material-row'); 
      const workDoneSelect = document.getElementById('work_done_checklist');
      const workDoneHidden = document.getElementById('work_done_hidden');
      const imagesContainer = document.getElementById('proof-images-container');
      const imagePathsInput = document.getElementById('proof_images_from_checklist');
      const unitSelect = document.getElementById('report_unit_id');
      const progInput = document.getElementById('progress_percentage');
      const errorBox = document.getElementById('report-error-box');
      const errorList = document.getElementById('report-error-list');

      // Function to update progress based on Unit's progress (if selected)
      function updateProgressFromUnit() {
          const selectedUnit = unitSelect.options[unitSelect.selectedIndex];
          console.log('Unit changed! Value:', selectedUnit.value, 'Text:', selectedUnit.textContent);
          console.log('data-progress:', selectedUnit.getAttribute('data-progress'));
          if (selectedUnit.value) {
              const progress = selectedUnit.getAttribute('data-progress') || '0';
              console.log('Setting progress to:', progress);
              progInput.value = progress;
          }
      }
      
      // Function to filter checklist items by selected unit
      function filterChecklistByUnit() {
          const selectedUnitId = unitSelect.value;
          const options = workDoneSelect.querySelectorAll('option');
          
          // Reset the work done selection
          workDoneSelect.value = '';
          workDoneHidden.value = '';
          imagesContainer.innerHTML = '<p id="image-placeholder" style="color:#6b7280;">Select a checklist item above to load its proof images.</p>';
          imagePathsInput.value = '';
          
          options.forEach(option => {
              if (option.value === '') {
                  // Keep the placeholder option visible
                  option.style.display = '';
                  return;
              }
              
              const optionUnitId = option.getAttribute('data-unit-id');
              if (selectedUnitId && optionUnitId === selectedUnitId) {
                  option.style.display = '';
              } else if (selectedUnitId) {
                  option.style.display = 'none';
              } else {
                  // If no unit selected, hide all options except placeholder
                  option.style.display = 'none';
              }
          });
      }
      
      // Use addEventListener for more reliable event binding
      unitSelect.addEventListener('change', function() {
          updateProgressFromUnit();
          filterChecklistByUnit();
      });
      
      // Call once on load for initial prefill (if a unit is pre-selected)
      updateProgressFromUnit();
      filterChecklistByUnit();

      // === Work Done (Checklist) Logic ===
      workDoneSelect.onchange = function() {
          const selected = this.options[this.selectedIndex];
          const itemDesc = selected.textContent.trim();
          // Data is now retrieved from data-images attribute (JSON array)
          let images = [];
          try {
              images = JSON.parse(selected.dataset.images || '[]');
          } catch(e) {
              images = [];
          }
          
          // 1. Update the hidden field for DB storage
          workDoneHidden.value = itemDesc; 

          // 2. Update Proof Images Display (now supports multiple images)
          imagesContainer.innerHTML = '';
          imagePathsInput.value = '';

          if (images.length > 0) {
              let imagesHtml = '<div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:10px;">';
              images.forEach((imgPath, index) => {
                  const fullUrl = BASE_IMAGE_URL + encodeURIComponent(imgPath);
                  imagesHtml += `
                      <div style="border:1px solid #e5e7eb; border-radius:8px; padding:8px; display:flex; gap:8px; align-items:center; flex:1; min-width:200px;">
                          <img src="${fullUrl}" style="width:50px; height:50px; object-fit:cover; border-radius:4px;">
                          <p style="margin:0; font-size:13px;">Proof ${index + 1}: ${itemDesc}</p>
                      </div>
                  `;
              });
              imagesHtml += '</div>';
              imagesContainer.innerHTML = imagesHtml;
              imagePathsInput.value = JSON.stringify(images); // Set all paths for PHP processing
          } else {
              imagesContainer.innerHTML = '<p id="image-placeholder" style="color:#6b7280;">No proof images found for this item.</p>';
          }
      }
      
      // === Materials Logic ===
      function rowTemplate(idx) {
        let opts = MATS_DATA.map(m => `
          <option value="${m.id}" data-rem="${m.remaining_quantity}" data-uom="${m.unit_of_measurement}">
            ${m.name} (rem: ${Math.floor(m.remaining_quantity)} ${m.unit_of_measurement})
          </option>`).join('');

        return `
          <div class="mat-row" style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
            <select name="material_id[]" class="mat-select" required style="flex:2;">
              <option value="">Select material</option>
              ${opts}
            </select>
            <input type="number" name="quantity_used[]" min="1" placeholder="Qty Used" required style="flex:1;">
            <span class="unit-label" style="min-width:40px; color:#555;"></span>
            <button type="button" class="remove-row btn-secondary" style="padding: 6px 10px;">Remove</button>
          </div>`;
      }

      addMatBtn.addEventListener('click', () => {
        materialsRows.insertAdjacentHTML('beforeend', rowTemplate(Date.now()));
      });

      materialsRows.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-row')) {
          e.target.closest('.mat-row').remove();
        }
      });

      materialsRows.addEventListener('change', (e) => {
        if (e.target.classList.contains('mat-select')) {
          const selected = e.target.options[e.target.selectedIndex];
          const uom = selected.dataset.uom || '';
          const unitLabel = e.target.closest('.mat-row').querySelector('.unit-label');
          unitLabel.textContent = uom ? `(${uom})` : '';
        }
      });


      // === Form Submission (AJAX) ===
      reportOverlayForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          // Clear previous errors
          errorBox.style.display = 'none';
          errorList.innerHTML = '';

          const formData = new FormData(this);
          
          // Client-side validation for Work Done selection
          if (!workDoneSelect.value) {
              errorList.innerHTML = '<li>Please select a Completed Checklist item for "Work Done."</li>';
              errorBox.style.display = 'block';
              return;
          }
          
          // Client-side validation for progress
          const progressVal = parseInt(progInput.value) || 0;
          if (progressVal < 0 || progressVal > 100) {
              errorList.innerHTML = '<li>Progress must be between 0 and 100%.</li>';
              errorBox.style.display = 'block';
              return;
          }


          fetch(this.action, { // Action points to ../reports/process_add_report.php
              method: 'POST',
              body: formData
          })
          .then(response => response.json())
          .then(data => {
              if (data.success) {
                  showToast('Report added successfully!', 'success');
                  toggleReportOverlay(false);
                  setTimeout(() => reloadWithTab('reports'), 1000); // Reload to reports tab
              } else {
                  // Display server-side errors
                  errorList.innerHTML = data.errors.map(err => `<li>${err}</li>`).join('');
                  errorBox.style.display = 'block';
              }
          })
          .catch(err => {
              // Display generic network/parsing error (e.g., HTTP 500)
              errorList.innerHTML = '<li>An unexpected network error occurred. Check server logs for HTTP 500 error.</li>';
              errorBox.style.display = 'block';
              console.error('AJAX Error:', err);
          });
      });

  }
  // --- END NEW REPORT FORM SETUP ---

  // ... (Your existing persistent search/sort logic) ...

});
</script>

<?php include '../includes/footer.php'; ?>
<?php 
// =======================================================================
// PHP END (CATCH BLOCK)
// =======================================================================

// END THE GLOBAL TRY BLOCK
} catch (Exception $e) {
    // If a non-database fatal error occurred, display a cleaner message
    echo '<div style="background:#f8d7da; color:#721c24; padding:20px; border:1px solid #f5c6cb; border-radius:8px; margin:50px auto; max-width:600px;">';
    echo '<h2>Fatal Error Encountered</h2>';
    echo '<p>There was an unhandled exception that stopped the page from loading.</p>';
    echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p><strong>Line:</strong> ' . htmlspecialchars($e->getLine()) . '</p>';
    echo '</div>';
    exit;
}
?>