<?php
// Set timezone immediately for consistent date handling
date_default_timezone_set('Asia/Manila'); // set timezone to PH

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// ✅ Get report ID - support both GET and POST for AJAX
$report_id = $_POST['report_id'] ?? $_GET['id'] ?? null;
if (!$report_id) {
  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Report not specified.']);
    exit;
  }
  die('Report not specified.');
}

// Ensure $report_id is an integer for secure binding
$report_id = intval($report_id);

// ✅ Fetch report info
$stmt = $conn->prepare("SELECT * FROM project_reports WHERE id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Report not found.']);
    exit;
  }
  die('Report not found.');
}

$project_id = $report['project_id'];

// === NEW: Calculate unit progress based on checklist completion ===
$unit_progress = 0;
if (!empty($report['unit_id'])) {
    $progress_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed
        FROM project_checklists 
        WHERE unit_id = ?
    ");
    $progress_stmt->bind_param('i', $report['unit_id']);
    $progress_stmt->execute();
    $progress_result = $progress_stmt->get_result()->fetch_assoc();
    $progress_stmt->close();
    
    $total_items = (int)($progress_result['total'] ?? 0);
    $completed_items = (int)($progress_result['completed'] ?? 0);
    
    // Calculate percentage
    $unit_progress = $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;
}
// ===================================================================

// === NEW: SAFE DATE PRE-FILL FOR INPUT ===
$report_date_value_for_input = date('m-d-Y'); // Default to today in case DB date is bad or new report

if (isset($report['report_date']) && $report['report_date'] && $report['report_date'] !== '0000-00-00') {
    // Format the valid DB date (Y-m-d) to the required input format (m-d-Y)
    $report_date_value_for_input = date('m-d-Y', strtotime($report['report_date']));
}

// If form submission failed, prioritize the POSTed date value (if it exists)
if (isset($_POST['report_date'])) {
    $report_date_value_for_input = htmlspecialchars($_POST['report_date']);
}
// =========================================


// ✅ Fetch related materials and images (MODIFIED: Using relational tables for materials)
$materials_result = $conn->query("
    SELECT 
        m.id AS material_id, 
        m.name AS material_name, 
        m.unit_of_measurement, 
        rmu.quantity_used
    FROM report_material_usage AS rmu
    INNER JOIN materials AS m ON m.id = rmu.material_id
    WHERE rmu.report_id = $report_id
");

// Fetch materials for new additions dropdown (all materials for this project)
$all_materials_res = $conn->query("SELECT id, name, remaining_quantity, unit_of_measurement FROM materials WHERE project_id = $project_id ORDER BY name");
$all_materials = [];
while ($m = $all_materials_res->fetch_assoc()) {
  // Casting to int here to resolve the original decimal issue in the JS array
  $m['remaining_quantity'] = (int)$m['remaining_quantity'];
  $all_materials[] = $m;
}

$images = $conn->query("SELECT * FROM report_images WHERE report_id = $report_id"); // FIX: Corrected table name


// ✅ Update report - support both regular form submission and AJAX (FormData doesn't include submit button)
if (isset($_POST['update_report']) || ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_date']))) {
  
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


  // Calculate progress based on checklist completion (not from POST)
  $progress_percentage = 0;
  if (!empty($report['unit_id'])) {
      $calc_stmt = $conn->prepare("
          SELECT 
              COUNT(*) as total,
              SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed
          FROM project_checklists 
          WHERE unit_id = ?
      ");
      $calc_stmt->bind_param('i', $report['unit_id']);
      $calc_stmt->execute();
      $calc_result = $calc_stmt->get_result()->fetch_assoc();
      $calc_stmt->close();
      
      $total_items = (int)($calc_result['total'] ?? 0);
      $completed_items = (int)($calc_result['completed'] ?? 0);
      
      $progress_percentage = $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;
  }
  
  $work_done = $_POST['work_done'];
  $remarks = $_POST['remarks'];

  // New Material Tracking fields from the form
  $material_ids_new = $_POST['new_material_id'] ?? [];
  $quantities_new   = $_POST['new_quantity_used'] ?? [];


  // === START TRANSACTION ===
  $conn->begin_transaction();
  try {

    // Final casting for binding
    $progress_percentage_int = (int)$progress_percentage;

    // 1. Update project report
    $update_stmt = $conn->prepare("
      UPDATE project_reports 
      SET report_date=?, progress_percentage=?, work_done=?, remarks=?, updated_at=NOW() 
      WHERE id=?
    ");
    
    // ✅ FIX: Corrected bind string to 'sissi' (5 characters for 5 question marks)
    // Parameters: report_date (s), progress_percentage (i), work_done (s), remarks (s), report_id (i)
    $update_stmt->bind_param("sissi", $report_date, $progress_percentage_int, $work_done, $remarks, $report_id);
    
    if (!$update_stmt->execute()) throw new Exception('Failed to update report record: ' . $update_stmt->error);
    $update_stmt->close();


    // 2. Update unit progress (as it might have changed) - only if unit_id exists
    if (!empty($report['unit_id'])) {
        [$ok, $msg] = update_unit_progress($conn, (int)$report['unit_id'], (int)$progress_percentage);
        if (!$ok) throw new Exception("Failed to update unit progress: $msg");
    }


    // 3. Handle NEW material usage (using UPSET logic)
    // MODIFIED: Using ON DUPLICATE KEY UPDATE as requested for upsert behavior
    $insMU = $conn->prepare("
      INSERT INTO report_material_usage (report_id, material_id, quantity_used)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE quantity_used = quantity_used + VALUES(quantity_used)
    ");
    if (!$insMU) throw new Exception('Material usage prepare failed: ' . $conn->error);
    
    for ($i = 0; $i < count($material_ids_new); $i++) {
        $mid = (int)$material_ids_new[$i];
        $qty = (int)($quantities_new[$i] ?? 0);
        if ($mid <= 0 || $qty <= 0) continue;

        // Deduct material quantity from stock
        [$ok, $err] = deduct_material_quantity($conn, $mid, $qty);
        if (!$ok) throw new Exception("Material deduction failed: $err");

        // Record usage in report_material_usage (uses upsert query)
        $insMU->bind_param('iii', $report_id, $mid, $qty);
        if (!$insMU->execute()) throw new Exception('Failed to record new material usage.');
    }
    $insMU->close();


    // 4. Handle proof images upload
    $uploaded = $_FILES['proof_images'] ?? null;
    if ($uploaded && !empty($uploaded['name'][0])) {
        $uploadDir = __DIR__ . '/report_images'; // Correct path relative to edit_report.php
        
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

            [$ok, $newName] = save_report_image($file, $uploadDir);
            if (!$ok) throw new Exception('Image upload failed: ' . $newName);

            $img = $conn->prepare("INSERT INTO report_images (report_id, image_path) VALUES (?, ?)"); // FIX: Corrected table name
            $img->bind_param('is', $report_id, $newName);
            if (!$img->execute()) throw new Exception('Failed to save image record: ' . $img->error);
        }
    }


    $conn->commit();
    // Log the edit action
    log_activity($conn, 'EDIT_REPORT', "Edited report ID: $report_id (Date: $report_date) for project ID: $project_id");
    
    if ($isAjax) {
      header('Content-Type: application/json');
      echo json_encode(['success' => true, 'message' => 'Report updated successfully!']);
      exit;
    }
    header("Location: ../modules/view_project.php?id=$project_id&tab=reports&status=success&message=" . urlencode("Report updated successfully!"));
    exit;

  } catch (Exception $e) {
    $conn->rollback();
    if ($isAjax) {
      header('Content-Type: application/json');
      echo json_encode(['success' => false, 'message' => $e->getMessage()]);
      exit;
    }
    // Add error message for display
    $report['error_message'] = $e->getMessage();
  }
}

// ✅ Delete proof image
if (isset($_GET['delete_image'])) {
  $img_id = intval($_GET['delete_image']);
  
  // FIX: Corrected table name and added transaction/file deletion logic
  $conn->begin_transaction();
  try {
      $path_stmt = $conn->prepare("SELECT image_path FROM report_images WHERE id = ?");
      $path_stmt->bind_param('i', $img_id);
      $path_stmt->execute();
      $path_res = $path_stmt->get_result();
      $image_path_row = $path_res->fetch_assoc();
      $path_stmt->close();

      if ($image_path_row) {
          $file_to_delete = __DIR__ . '/report_images/' . $image_path_row['image_path'];
          if (file_exists($file_to_delete)) {
              unlink($file_to_delete);
          }
      }

      $del_stmt = $conn->prepare("DELETE FROM report_images WHERE id = ?");
      $del_stmt->bind_param('i', $img_id);
      $del_stmt->execute();
      $del_stmt->close();
      
      $conn->commit();
      header("Location: edit_report.php?id=$report_id");
      exit;
  } catch (Exception $e) {
      $conn->rollback();
      die('Failed to delete image record and file: ' . $e->getMessage());
  }
}

// Fetch unit name for header display
$unit_name = '';
if (!empty($report['unit_id'])) {
    $u_stmt = $conn->prepare("SELECT name FROM project_units WHERE id = ?");
    $u_stmt->bind_param('i', $report['unit_id']);
    $u_stmt->execute();
    $u_res = $u_stmt->get_result()->fetch_assoc();
    $unit_name = $u_res['name'] ?? '';
}
$unit_display = $unit_name ? "Unit: " . htmlspecialchars($unit_name) : 'General Report';

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
    textarea,
    select { /* Added select */
      border: 1px solid #d1d5db;
      border-radius: 8px;
      padding: 8px 10px;
      font-size: 14px;
      background: #f9fafb;
    }

    input:focus,
    textarea:focus,
    select:focus { /* Added select */
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
    .material-item input[type="text"] { flex: 1; }
    .material-item input[type="number"] { width: 100px; }
    .material-item select { flex: 2; }
    .material-item .unit-display {
      line-height: 35px;
      color: #6b7280;
      font-size: 13px;
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

    .image-container {
        position: relative;
        display: inline-block;
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
      justify-content: flex-end;
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
      padding: 8px 16px;
      border-radius: 6px;
      line-height: 1.4;
    }

    .btn-cancel:hover {
      text-decoration: none;
      background: #e5e7eb;
    }
    
    .error-message {
        background: #f8d7da;
        color: #721c24;
        padding: 10px;
        border: 1px solid #f5c6cb;
        border-radius: 6px;
        margin-bottom: 15px;
    }

  </style>

  <script>
    const allMaterials = <?= json_encode($all_materials) ?>;

    function getMaterialUOM(id) {
        const mat = allMaterials.find(m => m.id == id);
        return mat ? mat.unit_of_measurement : '';
    }

    function addMaterial() {
      const container = document.getElementById("materials-container");
      const newRow = document.createElement("div");
      newRow.className = "material-item";
      
      // MODIFIED: Use Math.floor on remaining_quantity for display
      let options = allMaterials.map(m => 
          `<option value="${m.id}" data-uom="${m.unit_of_measurement}" data-rem="${m.remaining_quantity}">
             ${m.name} (Rem: ${Math.floor(m.remaining_quantity)} ${m.unit_of_measurement})
           </option>`).join('');

      newRow.innerHTML = `
        <select name="new_material_id[]" onchange="updateUOM(this)" required>
            <option value="">-- Select Material --</option>
            ${options}
        </select>
        <input type="number" name="new_quantity_used[]" min="1" placeholder="Qty Used" required style="width: 100px;">
        <span class="unit-display"></span>
        <button type="button" class="remove-material" onclick="this.parentElement.remove()">✕</button>
      `;
      container.appendChild(newRow);
    }

    function updateUOM(selectElement) {
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const uom = selectedOption.getAttribute('data-uom') || '';
        const unitDisplay = selectElement.closest('.material-item').querySelector('.unit-display');
        unitDisplay.textContent = uom;
    }


    function confirmDeleteImage(event, url) {
      event.preventDefault();
      if (confirm("Are you sure you want to delete this proof image?")) {
        window.location.href = url;
      }
    }
    
    // Ensure UOM is set for existing materials on load (if any)
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.material-item select').forEach(updateUOM);
    });
    
  </script>
</head>

<body>
  <div class="content-wrapper">
    <div class="form-card">
      <h2>Edit Report — <?= htmlspecialchars($project['name'] ?? 'Project') ?> | <?= $unit_display ?></h2>
      
      <?php if (isset($report['error_message'])): ?>
        <div class="error-message">
            <?= htmlspecialchars($report['error_message']); ?>
        </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
          <!-- ✅ CHANGE: Added (MM-DD-YYYY) to the label -->
          <label>Date (MM-DD-YYYY)</label>
          <!-- ✅ FIX: Use the new safe pre-fill variable -->
          <input type="text" name="report_date" value="<?= htmlspecialchars($report_date_value_for_input); ?>" required>
        </div>

        <div class="form-group">
          <label>Progress (%) - Based on Checklist Completion</label>
          <input type="number" name="progress_percentage" value="<?= $unit_progress; ?>" min="0" max="100" required readonly style="background-color:#e5e7eb; cursor:not-allowed;">
        </div>

        <div class="form-group">
          <label>Work Done</label>
          <textarea name="work_done" rows="3"><?= htmlspecialchars($report['work_done']); ?></textarea>
        </div>

        <div class="form-group">
          <label>Remarks</label>
          <textarea name="remarks" rows="3"><?= htmlspecialchars($report['remarks']); ?></textarea>
        </div>

        <h4>Materials Used (Current)</h4>
        <div class="materials-section">
          <!-- Existing Materials (Display only, assume deduction logic is separate/handled on initial insert) -->
          <?php if ($materials_result->num_rows > 0): ?>
            <?php while ($m = $materials_result->fetch_assoc()): ?>
              <div class="material-item">
                <!-- Display material name and quantity used -->
                <input type="text" value="<?= htmlspecialchars($m['material_name']); ?>" readonly style="flex:2; font-weight: 600;">
                <input type="text" value="<?= intval($m['quantity_used']); ?>" readonly style="width: 100px;">
                <span class="unit-display"><?= htmlspecialchars($m['unit_of_measurement']); ?></span>
                <!-- Since we are not implementing the complex logic of refunding/re-deducting stock here, 
                     we do not allow deletion/editing of existing usage via this form. -->
              </div>
            <?php endwhile; ?>
          <?php else: ?>
             <p style="color:#6b7280; font-size: 13px;">No materials were recorded for this report.</p>
          <?php endif; ?>
        </div>
        
        <h4>Add New Materials to Report (Deduct from stock)</h4>
        <div class="materials-section" id="materials-container">
          <!-- New materials will be added here via JS -->
        </div>
        <button type="button" class="btn-secondary" onclick="addMaterial()">+ Add Material</button>

        <div class="form-group">
          <label>Proof Images (JPG/PNG/WEBP) - Max: 5MB each</label>
          <div class="proof-images">
            <?php while ($img = $images->fetch_assoc()): ?>
              <div class="image-container">
                <!-- FIX: Corrected file path usage (assuming 'report_images' is next to edit_report.php) -->
                <img src="./report_images/<?= htmlspecialchars($img['image_path']); ?>" alt="Proof">
                <br>
                <!-- FIX: Corrected deletion link to use the correct variable $report_id and correct path -->
                <a href="#" onclick="confirmDeleteImage(event, '?id=<?= $report_id; ?>&delete_image=<?= $img['id']; ?>')" class="delete-img">Delete</a>
              </div>
            <?php endwhile; ?>
          </div>
          <input type="file" name="proof_images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
        </div>

        <div class="form-actions">
          <button type="submit" name="update_report" class="btn-primary">Update Report</button>
          <a href="../modules/view_project.php?id=<?= $project_id; ?>&tab=reports" class="btn-cancel">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>