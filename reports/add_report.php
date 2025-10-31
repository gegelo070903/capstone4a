<?php
ob_start(); // ✅ Start output buffering (prevents header warnings)

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
}

if (!$project) {
    echo '<div class="container"><p class="text-red-600">Invalid project.</p></div>';
    require_once __DIR__ . '/../includes/footer.php';
    ob_end_flush();
    exit;
}
$project_name = $project['name']; // Using a simple variable for clarity

// Fetch materials for dropdown
$mats = [];
$ms = $conn->prepare("SELECT id, name, remaining_quantity, unit_of_measurement FROM materials WHERE project_id = ? ORDER BY name");
$ms->bind_param('i', $project_id);
$ms->execute();
$matsRes = $ms->get_result();
while ($r = $matsRes->fetch_assoc()) $mats[] = $r;

$errors = [];

// --- FORM PROCESSING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid session token. Please reload the page.';
    } else {
        // Convert user input MM-DD-YYYY → YYYY-MM-DD for DB
        if (!empty($_POST['report_date'])) {
            $date_in = str_replace('/', '-', $_POST['report_date']); // allow both / and -
            $timestamp = strtotime($date_in);
            $report_date = $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
        } else {
            $report_date = date('Y-m-d');
        }

        $progress_percentage = max(0, min(100, (int)($_POST['progress_percentage'] ?? 0)));
        // FIX: Retrieving all fields from the POST data
        $work_done = trim($_POST['work_done'] ?? ''); 
        $remarks = trim($_POST['remarks'] ?? '');
        $created_by = $_SESSION['username'];

        $material_ids = $_POST['material_id'] ?? [];
        $quantities   = $_POST['quantity_used'] ?? [];

        if ($work_done === '') $errors[] = "Please describe today's work.";
        
        // Validation moved to form processing logic (where it should be)
        // You might want to re-check if your form elements are named correctly
        if ($progress_percentage < 0 || $progress_percentage > 100) $errors[] = "Progress must be between 0 and 100%.";

        if (!$errors) {
            try {
                $conn->begin_transaction();

                // Insert report
                $stmt = $conn->prepare("INSERT INTO project_reports (project_id, report_date, progress_percentage, work_done, remarks, created_by)
                                        VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isisss', $project_id, $report_date, $progress_percentage, $work_done, $remarks, $created_by);
                if (!$stmt->execute()) throw new Exception('Failed to insert report.');
                $report_id = $stmt->insert_id;

                // Handle image uploads
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

                        // ✅ Corrected to use image_path column
                        $img = $conn->prepare("INSERT INTO report_images (report_id, image_path) VALUES (?, ?)");
                        $img->bind_param('is', $report_id, $res);
                        if (!$img->execute()) throw new Exception('Failed to save image record.');
                    }
                }

                // Deduct materials and record usage
                if (!empty($material_ids) && is_array($material_ids)) {
                    $insMU = $conn->prepare("INSERT INTO report_material_usage (report_id, material_id, quantity_used) VALUES (?, ?, ?)");
                    for ($i = 0; $i < count($material_ids); $i++) {
                        $mid = (int)$material_ids[$i];
                        $qty = (int)($quantities[$i] ?? 0);
                        if ($mid <= 0 || $qty <= 0) continue;

                        [$ok, $err] = deduct_material_quantity($conn, $mid, $qty);
                        if (!$ok) throw new Exception("Material deduction failed: $err");

                        $insMU->bind_param('iii', $report_id, $mid, $qty);
                        if (!$insMU->execute()) throw new Exception('Failed to record material usage.');
                    }
                }

                $conn->commit();
                header('Location: /capstone/reports/view_report.php?id=' . $report_id);
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = $e->getMessage();
            }
        }
    }
}
?>
<!-- START REPLACEMENT BLOCK -->
<div class="content-wrapper">
  <div class="form-card">
    <div class="form-header">
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

        <div class="form-group">
          <label for="report_date">Date (MM-DD-YYYY)</label>
          <input type="text" id="report_date" name="report_date" value="<?= date('m-d-Y'); ?>" required>
        </div>

        <div class="form-group">
          <label for="progress_percentage">Progress (%)</label>
          <input type="number" id="progress_percentage" name="progress_percentage" value="0" min="0" max="100" required>
        </div>

        <div class="form-group">
          <label for="work_done">Work Done</label>
          <textarea id="work_done" name="work_done" rows="3"></textarea>
        </div>

        <div class="form-group">
          <label for="remarks">Remarks</label>
          <textarea id="remarks" name="remarks" rows="3"></textarea>
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
          <a href="../modules/view_project.php?id=<?= $project_id; ?>" class="btn-cancel">Cancel</a>
        </div>
    </form>
  </div>
</div>

<style>
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
  padding: 10px; /* Reduced vertical padding */
}

.form-card {
  background: #fff;
  border-radius: 12px;
  padding: 20px 25px; /* Reduced internal padding */
  box-shadow: 0 4px 10px rgba(0,0,0,0.05);
  max-width: 800px;
  margin: 0 auto;
}

/* === Header === */
.form-header h2 {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 20px;
}

/* === Fields === */
.form-group {
  display: flex;
  flex-direction: column;
  margin-bottom: 18px;
}

label {
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}

input[type="text"],
input[type="number"],
textarea,
select {
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 14px;
  color: #111827;
  background-color: #f9fafb;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

input:focus,
textarea:focus,
select:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
  outline: none;
}

/* === Section Divider === */
.section-divider {
  border-bottom: 1px solid #e5e7eb;
  margin: 25px 0 15px;
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

.btn-cancel {
  color: #6b7280;
  margin-left: 10px;
  text-decoration: none;
  font-weight: 600;
}

.btn-cancel:hover {
  text-decoration: underline;
}

/* === Footer Buttons === */
.form-actions {
  display: flex;
  justify-content: flex-end; /* FIX: Align buttons to the right */
  align-items: center;
  margin-top: 20px;
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
// FIX: Changed selector to match new HTML ID
const rows = document.getElementById('materials-rows'); 
// FIX: Changed selector to match new HTML ID
const addBtn = document.getElementById('add-material-row'); 

function rowTemplate(idx) {
  let opts = mats.map(m => `
    <option value="${m.id}" data-rem="${m.remaining_quantity}" data-uom="${m.unit_of_measurement}">
      ${m.name} (rem: ${m.remaining_quantity} ${m.unit_of_measurement})
    </option>`).join('');

  return `
    <div class="mat-row" style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
      <select name="material_id[]" class="mat-select" required>
        <option value="">Select material</option>
        ${opts}
      </select>
      <input type="number" name="quantity_used[]" min="1" placeholder="Enter Quantity Used" required style="flex:1;">
      <span class="unit-label" style="min-width:40px; color:#555;"></span>
      <button type="button" class="remove-row">Remove</button>
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