<?php
ob_start(); // ✅ Start output buffering (prevents header warnings)

// Set timezone immediately for consistent date handling
date_default_timezone_set('Asia/Manila'); // set timezone to PH

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['username'])) {
    header('Location: /capstone/users/login.php');
    exit;
}

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

// Fetch project info
$project = null;
if ($project_id > 0) {
    $ps = $conn->prepare("SELECT * FROM projects WHERE id = ?");
    $ps->bind_param('i', $project_id);
    $ps->execute();
    $project = $ps->get_result()->fetch_assoc();
    $ps->close();
}

if (!$project) {
    echo '<div class="container"><p class="text-red-600">Invalid project.</p></div>';
    require_once __DIR__ . '/../includes/footer.php';
    ob_end_flush();
    exit;
}
$project_name = $project['name']; // Using a simple variable for clarity

// 🎯 Fetch project units for the dropdown
$units = [];
$unit_stmt = $conn->prepare("SELECT id, name, progress FROM project_units WHERE project_id = ? ORDER BY name ASC");
$unit_stmt->bind_param('i', $project_id);
$unit_stmt->execute();
$unit_result = $unit_stmt->get_result();
while ($row = $unit_result->fetch_assoc()) {
    $units[] = $row;
}
$unit_stmt->close();


// 1) Define a stable selected unit variable up top
// Determine which unit should be selected in the form
$selected_unit_id = 0;
$prefill_progress = 0;

// If the form was posted and failed validation, keep the user's choice
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_unit_id = (int)($_POST['unit_id'] ?? 0);
} else {
    // First GET load (e.g., add_report.php?project_id=11&unit_id=5) or no unit passed
    $selected_unit_id = (int)($_GET['unit_id'] ?? 0);
}

// Optional: prefill progress from the chosen unit so "Progress (%)" matches unit progress
if ($selected_unit_id > 0) {
    $pu = $conn->prepare("SELECT progress FROM project_units WHERE id = ? AND project_id = ?");
    $pu->bind_param('ii', $selected_unit_id, $project_id);
    $pu->execute();
    // Using fetch_assoc for better safety/readability than directly indexing the array
    $progress_row = $pu->get_result()->fetch_assoc();
    $prefill_progress = (int)($progress_row['progress'] ?? 0);
    $pu->close();
}


// Fetch materials for dropdown
$mats = [];
$ms = $conn->prepare("SELECT id, name, remaining_quantity, unit_of_measurement FROM materials WHERE project_id = ? ORDER BY name");
$ms->bind_param('i', $project_id);
$ms->execute();
$matsRes = $ms->get_result();
while ($r = $matsRes->fetch_assoc()) {
    $r['remaining_quantity'] = (int)$r['remaining_quantity'];
    $mats[] = $r;
}
$ms->close();

$errors = [];


// --- FORM PROCESSING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 4) In your POST handler, use the stable variable
    // Read $unit_id from POST again to ensure it's validated/used correctly in this block
    $unit_id = (int)($_POST['unit_id'] ?? 0); 

    if (!verify_csrf_token($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid session token. Please reload the page.';
    } else {
        
        // ===============================================
        // ✅ REPLACEMENT START: UNIFIED DATE LOGIC BLOCK (FIXED FOR MM-DD-YYYY)
        // ===============================================
        date_default_timezone_set('Asia/Manila');

        if (!empty($_POST['report_date'])) {
            $date_in = trim($_POST['report_date']);

            // ✅ FIX: Use createFromFormat to explicitly parse as MM-DD-YYYY
            $dt = DateTime::createFromFormat('m-d-Y', $date_in, new DateTimeZone('Asia/Manila'));
            
            // Check if parsing succeeded and the date is valid
            if ($dt !== false && $dt->format('m-d-Y') === $date_in) {
                $report_date = $dt->format('Y-m-d');
            } else {
                // Fallback: Use today's PH date if the input is malformed
                $report_date = date('Y-m-d');
            }
        } else {
            // Default when field is blank
            $report_date = date('Y-m-d');
        }
        // ===============================================
        // ✅ REPLACEMENT END
        // ===============================================

        // 5) Get Progress from POST (Reverting to editable logic)
        $progress_percentage = max(0, min(100, (int)($_POST['progress_percentage'] ?? 0)));
        
        $work_done = trim($_POST['work_done'] ?? ''); 
        $remarks = trim($_POST['remarks'] ?? '');
        $created_by = $_SESSION['username'];

        $material_ids = $_POST['material_id'] ?? [];
        $quantities   = $_POST['quantity_used'] ?? [];

        // 🎯 Unit ID validation
        if ($unit_id <= 0) $errors[] = "Please select which unit this report is for.";
        if ($work_done === '') $errors[] = "Please describe today's work.";
        if ($progress_percentage < 0 || $progress_percentage > 100) $errors[] = "Progress must be between 0 and 100%."; // Re-added progress validation
        

        if (!$errors) {
            try {
                $conn->begin_transaction();

                // 🎯 INSERT QUERY: Uses $progress_percentage from POST
                $stmt = $conn->prepare("
                    INSERT INTO project_reports 
                      (project_id, unit_id, report_date, progress_percentage, work_done, remarks, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                // Using requested bind string 'iisisss'
                $stmt->bind_param('iisisss', $project_id, $unit_id, $report_date, $progress_percentage, $work_done, $remarks, $created_by);
                
                if (!$stmt->execute()) throw new Exception('Failed to insert report: ' . $stmt->error);
                $report_id = $stmt->insert_id;

                // Handle image uploads... 
                $uploaded = $_FILES['proof_images'] ?? null;
                if ($uploaded && is_array($uploaded['name'])) {
                    $count = count($uploaded['name']);
                    for ($i = 0; $i < $count; $i++) {
                        if ($uploaded['error'][$i] === UPLOAD_ERR_NO_FILE) continue;

                        $file = [
                            'name' => $uploaded['name'][$i],
                            'type' => $uploaded['type'][$i] ?? '',
                            'tmp_name' => $uploaded['tmp_name'][$i],
                            'error' => $uploaded['error'][$i],
                            'size' => $uploaded['size'][$i],
                        ];

                        [$ok, $res] = save_report_image($file, __DIR__ . '/report_images');
                        if (!$ok) throw new Exception('Image upload failed: ' . $res);

                        $img = $conn->prepare("INSERT INTO report_images (report_id, image_path) VALUES (?, ?)");
                        $img->bind_param('is', $report_id, $res);
                        if (!$img->execute()) throw new Exception('Failed to save image record.');
                    }
                }

                // Deduct materials and record usage (MODIFIED: Using ON DUPLICATE KEY UPDATE)
                if (!empty($material_ids) && is_array($material_ids)) {
                    // MODIFIED: Using ON DUPLICATE KEY UPDATE query
                    $insMU = $conn->prepare("
                      INSERT INTO report_material_usage (report_id, material_id, quantity_used)
                      VALUES (?, ?, ?)
                      ON DUPLICATE KEY UPDATE quantity_used = quantity_used + VALUES(quantity_used)
                    ");
                    if (!$insMU) throw new Exception('Material usage prepare failed: ' . $conn->error);
                    
                    for ($i = 0; $i < count($material_ids); $i++) {
                        $mid = (int)$material_ids[$i];
                        $qty = (int)($quantities[$i] ?? 0);
                        if ($mid <= 0 || $qty <= 0) continue;

                        [$ok, $err] = deduct_material_quantity($conn, $mid, $qty);
                        if (!$ok) throw new Exception("Material deduction failed: $err");

                        $insMU->bind_param('iii', $report_id, $mid, $qty);
                        if (!$insMU->execute()) throw new Exception('Failed to record material usage.');
                    }
                    $insMU->close();
                }
                
                // ✅ NEW: Automatically update the unit's progress
                [$ok, $msg] = update_unit_progress($conn, $unit_id, $progress_percentage);
                if (!$ok) throw new Exception("Failed to update unit progress: $msg");

                $conn->commit();
                // ✅ REDIRECT IS CORRECT: Go back to the 'reports' tab
                header("Location: ../modules/view_project.php?id=$project_id&tab=reports");
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = $e->getMessage();
            }
        }
    }
}

// 3) Prefill the Progress input (only on GET; keep POST value after errors)
// If POST failed validation, keep user-entered value; otherwise use prefill from unit
$progress_value = isset($_POST['progress_percentage'])
    ? (int)$_POST['progress_percentage']
    : $prefill_progress;

// Set default value for date if POST failed
$report_date_value = htmlspecialchars($_POST['report_date'] ?? date('m-d-Y'));
?>
<!-- START REPLACEMENT BLOCK -->
<div class="content-wrapper">
  <div class="form-card">
    <div class="form-header-row">
      <h2>Add Report — Project: <?= htmlspecialchars($project_name); ?></h2>
    </div>

    <?php if ($errors): ?>
      <div class="error-box">
          <ul style="margin:0 0 0 18px;">
              <?php foreach ($errors as $er): ?><li><?= htmlspecialchars($er) ?></li><?php endforeach; ?>
          </ul>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <!-- 🎯 NEW: Select Unit Block -->
        <div class="form-group">
          <label for="unit_id">Select Unit</label>
          <!-- 2) Use the stable variable in the Unit dropdown -->
          <select id="unit_id" name="unit_id" required>
            <option value="">— Select Unit —</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>" <?= $selected_unit_id == (int)$u['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- END NEW BLOCK -->

        <div class="form-group">
          <!-- ✅ CHANGE: Added (MM-DD-YYYY) to the label -->
          <label for="report_date">Date (MM-DD-YYYY)</label>
          <input type="text" id="report_date" name="report_date" value="<?= $report_date_value; ?>" required>
        </div>

        <div class="form-group">
          <label for="progress_percentage">Progress (%)</label>
          <!-- 3) Prefill the Progress input (re-enabled as editable) -->
          <input type="number" id="progress_percentage" name="progress_percentage"
                 value="<?= $progress_value ?>" min="0" max="100" required>
        </div>

        <div class="form-group">
          <label for="work_done">Work Done</label>
          <textarea id="work_done" name="work_done" rows="3"><?= htmlspecialchars($_POST['work_done'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label for="remarks">Remarks</label>
          <textarea id="remarks" name="remarks" rows="3"><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
        </div>

        <div class="section-divider"></div>

        <h4>Materials Used</h4>
        <!-- Original container kept for JS compatibility -->
        <div id="materials-rows"></div> 
        <button type="button" id="add-material-row" class="btn-secondary">+ Add Material</button>


        <div class="section-divider"></div>

        <div class="form-group">
          <label for="proof_images">Proof Images (JPG/PNG/WEBP, multiple)</label>
          <input type="file" id="proof_images" name="proof_images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
        </div>

        <div class="form-actions">
          <button type="submit" name="save_report" class="btn-primary">Save Report</button>
          <!-- ✅ UPDATED CANCEL LINK: Using correct class and redirect -->
          <a href="../modules/view_project.php?id=<?= $project_id ?>&tab=reports" class="btn-cancel">Cancel</a>
        </div>
    </form>
  </div>
</div>

<style>
/* === Scrollbar Fix: Applied to body/html to force no-scroll === */
html, body {
  /* Removed height: 100%; and overflow-y: hidden; */
  margin: 0; /* Remove default browser margin */
  padding: 0; /* Remove default browser padding */
}

/* === Error Box Style === */
.error-box {
  background:#f8d7da; 
  color:#721c24; 
  padding:10px; 
  border:1px solid #f5c6cb; 
  border-radius: 6px;
  margin-bottom: 20px;
}

/* === Container Layout === */
.content-wrapper {
  /* Increased bottom padding to ensure form footer is always visible */
  padding: 10px 10px 40px 10px; 
}

.form-card {
  background: #fff;
  border-radius: 12px;
  /* Reduced internal padding */
  padding: 15px 25px; 
  box-shadow: 0 4px 10px rgba(0,0,0,0.05);
  max-width: 800px;
  /* Resetting margin to be auto on sides, with 0 on top/bottom */
  margin: 0 auto; 
}

/* === Header === */
.form-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.form-header-row h2 {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 0; /* Removed bottom margin from h2 inside the flex container */
}
/* === Fields === */
.form-group {
  display: flex;
  flex-direction: column;
  /* FIX: Reduced margin-bottom */
  margin-bottom: 12px; 
}

label {
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}

input[type="text"],
input[type="number"],
textarea {
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 14px;
  color: #111827;
  background-color: #f9fafb;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

/* 🎯 NEW/UPDATED SELECT STYLE */
select {
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 14px;
  background-color: #f9fafb;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
select:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
  outline: none;
}
/* END NEW/UPDATED SELECT STYLE */

input:focus,
textarea:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
  outline: none;
}

/* === Section Divider === */
.section-divider {
  border-bottom: 1px solid #e5e7eb;
  /* FIX: Reduced vertical margins */
  margin: 15px 0 10px;
}

/* === Buttons === */
.btn-primary {
  background-color: #2563eb;
  color: white;
  font-weight: 600;
  border: none;
  border-radius: 6px;
  padding: 8px 16px;
  cursor: pointer;
  transition: background-color 0.2s ease;
  text-decoration: none;
}

.btn-primary:hover {
  background-color: #1d4ed8;
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

/* UPDATED CANCEL BUTTON STYLE */
.btn-cancel {
  background-color: #6b7280;
  color: #fff;
  font-weight: 600;
  border: none;
  border-radius: 6px;
  padding: 8px 16px;
  cursor: pointer;
  transition: background-color 0.2s ease;
  text-decoration: none;
}

.btn-cancel:hover {
  background-color: #4b5563;
}

/* === Footer Buttons === */
.form-actions {
  display: flex;
  justify-content: flex-end; /* FIX: Align buttons to the right */
  align-items: center;
  /* FIX: Reduced margin-top */
  margin-top: 15px; 
  gap: 10px;
}

/* === File Input === */
input[type="file"] {
  margin-top: 6px;
}
</style>

<!-- END REPLACEMENT BLOCK -->

<script>
const mats = <?= json_encode($mats) ?>;
const rows = document.getElementById('materials-rows'); 
const addBtn = document.getElementById('add-material-row'); 

// The JS progress logic is now removed as the field is user-editable and prefilled by PHP on load.

function rowTemplate(idx) {
  let opts = mats.map(m => `
    <option value="${m.id}" data-rem="${m.remaining_quantity}" data-uom="${m.unit_of_measurement}">
      ${m.name} (rem: ${Math.floor(m.remaining_quantity)} ${m.unit_of_measurement})
    </option>`).join('');

  return `
    <div class="mat-row" style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
      <select name="material_id[]" class="mat-select" required>
        <option value="">Select material</option>
        ${opts}
      </select>
      <input type="number" name="quantity_used[]" min="1" placeholder="Enter Quantity Used" required style="flex:1;">
      <span class="unit-label" style="min-width:40px; color:#555;"></span>
      <button type="button" class="remove-row btn-secondary" style="padding: 6px 10px;">Remove</button>
    </div>`;
}

// Add new material row
addBtn.addEventListener('click', () => {
  rows.insertAdjacentHTML('beforeend', rowTemplate(Date.now()));
});

// Remove row handler
rows.addEventListener('click', (e) => {
  if (e.target.classList.contains('remove-row')) {
    e.target.closest('.mat-row').remove();
  }
});

// When user selects a material, show its unit beside input
rows.addEventListener('change', (e) => {
  if (e.target.classList.contains('mat-select')) {
    const selected = e.target.options[e.target.selectedIndex];
    const uom = selected.dataset.uom || '';
    const unitLabel = e.target.closest('.mat-row').querySelector('.unit-label');
    unitLabel.textContent = uom ? `(${uom})` : '';
  }
});
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush(); // ✅ End output buffering
?>