<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../users/login.php");
    exit();
}

if (!isset($_GET['unit_id']) || !isset($_GET['project_id'])) {
    echo "<h3 style='color:red;'>Invalid parameters.</h3>";
    exit();
}

$unit_id = (int)$_GET['unit_id'];
$project_id = (int)$_GET['project_id'];

// Fetch project and unit details
$project = $conn->query("SELECT * FROM projects WHERE id = $project_id LIMIT 1")->fetch_assoc();
$unit = $conn->query("SELECT * FROM project_units WHERE id = $unit_id LIMIT 1")->fetch_assoc();

if (!$project || !$unit) {
    echo "<h3 style='color:red;'>Data not found.</h3>";
    exit();
}

// Fetch reports for this unit
$query = "SELECT * FROM project_reports WHERE unit_id = $unit_id ORDER BY report_date DESC";
$reports = $conn->query($query);
?>

<div class="content-wrapper">

  <!-- 📁 Header Container -->
  <div class="header-container">
    <div class="reports-header">
      <div>
        <h2><?= htmlspecialchars($unit['name']) ?> — Reports</h2>
        <p class="subtitle">
          Project: <?= htmlspecialchars($project['name']) ?> <br>
          Location: <?= htmlspecialchars($project['location']) ?>
        </p>
      </div>

      <div class="header-buttons">
        <button class="btn-back" onclick="window.location.href='view_units.php?project_id=<?= $project_id ?>'">
          <i class="fas fa-arrow-left"></i> Back to Units
        </button>
        <button class="btn-generate" 
          onclick="window.location.href='generate_report_pdf.php?unit_id=<?= $unit_id ?>&project_id=<?= $project_id ?>'">
          Generate Unit PDF
        </button>
      </div>
    </div>
  </div>

  <!-- 📊 Reports List -->
  <div class="reports-list">
    <?php if ($reports && $reports->num_rows > 0): ?>
      <?php while ($r = $reports->fetch_assoc()): ?>
        <div class="report-card" onclick="openViewReportOverlay(<?= $r['id'] ?>)">
          <div class="report-header">
            <h4><?= date('F d, Y', strtotime($r['report_date'])) ?></h4>
            <span class="progress-badge"><?= $r['progress_percentage'] ?>%</span>
          </div>

          <p><strong>Work Done:</strong> <?= nl2br(htmlspecialchars($r['work_done'])) ?></p>

          <?php if (!empty($r['remarks'])): ?>
            <p><strong>Remarks:</strong> <?= nl2br(htmlspecialchars($r['remarks'])) ?></p>
          <?php endif; ?>

          <p class="created-by">Created by: <?= htmlspecialchars($r['created_by']) ?></p>

          <div class="report-actions" onclick="event.stopPropagation();">
            <button type="button" class="btn-edit" onclick="openEditReportOverlay(<?= $r['id'] ?>)">Edit</button>
            <a href="delete_report.php?id=<?= $r['id'] ?>&from=reports&unit_id=<?= $unit_id ?>&project_id=<?= $project_id ?>" 
               onclick="return confirm('Are you sure you want to delete this report?');" 
               class="btn-delete">Delete</a>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="no-data">No reports found for this unit yet.</p>
    <?php endif; ?>
  </div>
</div>

<style>
.content-wrapper {
  padding: 20px;
  background: #f8fafc;
}

/* === Header Container === */
.header-container {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 18px 24px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  margin-bottom: 25px;
  position: sticky;
  top: 0;
  z-index: 100;
}

.reports-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.reports-header h2 {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.subtitle {
  color: #6b7280;
  font-size: 14px;
  margin-top: 4px;
}
.btn-generate {
  background-color: #2563eb;
  color: #fff;
  border: none;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
}
.btn-generate:hover {
  background-color: #1d4ed8;
}

/* === Back Button === */
.btn-back {
  background: #6b7280;
  color: #fff;
  padding: 8px 14px;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  font-size: 14px;
  transition: background-color 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.btn-back:hover {
  background: #4b5563;
}

/* === Header Buttons Container === */
.header-buttons {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

/* === Report Cards === */
.reports-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.report-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 16px 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
  transition: all 0.2s ease;
  cursor: pointer;
}
.report-card:hover {
  border-color: #2563eb;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
}
.report-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}
.report-header h4 {
  font-size: 16px;
  color: #111827;
  margin: 0;
}
.progress-badge {
  background-color: #2563eb;
  color: #fff;
  padding: 3px 8px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
}
.report-card p {
  font-size: 14px;
  color: #374151;
  margin: 4px 0;
}
.created-by {
  color: #6b7280;
  font-size: 13px;
  margin-top: 8px;
}

/* === Buttons === */
.report-actions {
  display: flex;
  gap: 8px;
  margin-top: 10px;
}
.btn-view, .btn-edit, .btn-delete {
  text-decoration: none;
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: background-color 0.2s;
}
.btn-view {
  background-color: #2563eb;
  color: white;
}
.btn-view:hover { background-color: #1d4ed8; }
.btn-edit {
  background-color: #f59e0b;
  color: white;
}
.btn-edit:hover { background-color: #d97706; }
.btn-delete {
  background-color: #ef4444;
  color: white;
}
.btn-delete:hover { background-color: #dc2626; }

.no-data {
  text-align: center;
  color: #6b7280;
  font-style: italic;
}

/* === OVERLAY STYLES === */
.overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1000;
  justify-content: center;
  align-items: center;
  padding: 20px;
  box-sizing: border-box;
}

.overlay-card {
  background: #fff;
  border-radius: 12px;
  padding: 25px 30px;
  width: 100%;
  max-width: 700px;
  max-height: 90vh;
  overflow-y: auto;
  position: relative;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.overlay-title {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 20px;
}

.close-btn {
  position: absolute;
  top: 15px;
  right: 15px;
  background: #f3f4f6;
  border: none;
  font-size: 18px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}

.close-btn:hover {
  background: #e5e7eb;
}

.section-divider {
  border-bottom: 1px solid #e5e7eb;
  margin: 15px 0;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  font-weight: 600;
  color: #374151;
  margin-bottom: 5px;
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  box-sizing: border-box;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
}

.btn-primary {
  background-color: #2563eb;
  color: white;
  font-weight: 600;
  border: none;
  border-radius: 6px;
  padding: 10px 18px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.btn-primary:hover {
  background-color: #1d4ed8;
}

.btn-cancel {
  background-color: #f3f4f6;
  color: #374151;
  font-weight: 600;
  border: none;
  border-radius: 6px;
  padding: 10px 18px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.btn-cancel:hover {
  background-color: #e5e7eb;
}

.btn-secondary {
  background-color: #6b7280;
  color: white;
  font-weight: 500;
  border: none;
  border-radius: 6px;
  padding: 8px 14px;
  cursor: pointer;
  font-size: 13px;
  transition: background-color 0.2s;
}

.btn-secondary:hover {
  background-color: #4b5563;
}

.error-box {
  background: #fee2e2;
  border: 1px solid #fca5a5;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 15px;
  color: #dc2626;
}

.material-row {
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
  align-items: center;
}

.material-row select {
  flex: 2;
}

.material-row input {
  flex: 1;
}

.material-row .unit-display {
  flex: 0.5;
  color: #6b7280;
  font-size: 13px;
}

.material-row .btn-remove {
  background: #ef4444;
  color: white;
  border: none;
  border-radius: 4px;
  padding: 6px 10px;
  cursor: pointer;
  font-size: 12px;
}

.material-row .btn-remove:hover {
  background: #dc2626;
}
</style>

<!-- ======================================================= -->
<!-- VIEW REPORT OVERLAY -->
<!-- ======================================================= -->
<div class="overlay" id="viewReportOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleViewReportOverlay(false)">✕</button>
    <h3 class="overlay-title" id="view-report-title">View Report</h3>

    <div class="report-details" id="view-report-content">
      <p style="color:#6b7280;">Loading report...</p>
    </div>

    <div class="section-divider"></div>

    <div class="form-actions">
      <button type="button" class="btn-primary" id="view-report-edit-btn">Edit Report</button>
      <button type="button" class="btn-cancel" onclick="toggleViewReportOverlay(false)">Close</button>
    </div>
  </div>
</div>

<!-- ======================================================= -->
<!-- EDIT REPORT OVERLAY -->
<!-- ======================================================= -->
<div class="overlay" id="editReportOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleEditReportOverlay(false)">✕</button>
    <h3 class="overlay-title">Edit Report — <?= htmlspecialchars($unit['name']) ?></h3>

    <div class="error-box" id="edit-report-error-box" style="display:none;">
      <ul id="edit-report-error-list" style="margin:0 0 0 18px;"></ul>
    </div>

    <form id="editReportForm" method="post" enctype="multipart/form-data">
      <input type="hidden" name="report_id" id="edit_report_id">
      <input type="hidden" name="project_id" value="<?= $project_id ?>">
      <input type="hidden" name="from" value="reports">
      <input type="hidden" name="unit_id" value="<?= $unit_id ?>">

      <div class="form-grid">
        <!-- Date -->
        <div class="form-group">
          <label for="edit_report_date">Date (MM-DD-YYYY)</label>
          <input type="text" id="edit_report_date" name="report_date" required placeholder="MM-DD-YYYY">
        </div>

        <!-- Progress -->
        <div class="form-group">
          <label for="edit_progress_percentage">Progress (%) - Based on Checklist</label>
          <input type="number" id="edit_progress_percentage" name="progress_percentage"
                 min="0" max="100" required readonly style="background-color:#e5e7eb; cursor:not-allowed;">
        </div>
      </div>

      <div class="section-divider"></div>

      <!-- Work Done -->
      <div class="form-group">
        <label for="edit_work_done">Work Done</label>
        <input type="text" id="edit_work_done" name="work_done" required>
      </div>

      <!-- Remarks -->
      <div class="form-group">
        <label for="edit_remarks">Remarks (Optional)</label>
        <textarea id="edit_remarks" name="remarks" rows="3"></textarea>
      </div>

      <div class="section-divider"></div>

      <!-- Existing Materials Used -->
      <h4>Current Materials Used</h4>
      <div id="edit-existing-materials" style="margin-bottom:15px; color:#6b7280;">
        <p>Loading...</p>
      </div>

      <!-- Add New Materials -->
      <h4>Add More Materials</h4>
      <div id="edit-materials-rows"></div>
      <button type="button" class="btn-secondary" id="edit-add-material-row">+ Add Material</button>

      <div class="section-divider"></div>

      <!-- Existing Images -->
      <h4>Current Proof Images</h4>
      <div id="edit-existing-images" style="margin-bottom:15px;">
        <p style="color:#6b7280;">Loading...</p>
      </div>

      <!-- Upload New Images -->
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

<script>
// ======================================================
// Materials data for dropdown (fetched from PHP)
// ======================================================
<?php
// Fetch materials for this project for the dropdown
$materials_stmt = $conn->prepare("SELECT id, name, remaining_quantity, unit_of_measurement FROM materials WHERE project_id = ? ORDER BY name");
$materials_stmt->bind_param("i", $project_id);
$materials_stmt->execute();
$materials_result = $materials_stmt->get_result();
$all_materials = [];
while ($m = $materials_result->fetch_assoc()) {
    $m['remaining_quantity'] = (int)$m['remaining_quantity'];
    $all_materials[] = $m;
}
$materials_stmt->close();
?>
const allMaterials = <?= json_encode($all_materials) ?>;
const unitId = <?= $unit_id ?>;
const projectId = <?= $project_id ?>;

// ======================================================
// Toast notification function
// ======================================================
function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  toast.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 9999;
    animation: slideIn 0.3s ease;
    background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
  `;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}

// ======================================================
// VIEW REPORT OVERLAY FUNCTIONS
// ======================================================
function toggleViewReportOverlay(show) {
  document.getElementById('viewReportOverlay').style.display = show ? 'flex' : 'none';
  if (!show) {
    document.getElementById('view-report-content').innerHTML = '<p style="color:#6b7280;">Loading report...</p>';
  }
}

function openViewReportOverlay(reportId) {
  toggleViewReportOverlay(true);
  
  fetch(`get_report.php?id=${reportId}`)
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        showToast(data.message || 'Failed to load report data', 'error');
        toggleViewReportOverlay(false);
        return;
      }
      
      const r = data.report;
      
      // Update title
      document.getElementById('view-report-title').textContent = `Report — ${r.unit_name || 'General'}`;
      
      // Format date
      let displayDate = 'Date Not Set';
      if (r.report_date && r.report_date !== '0000-00-00') {
        const parts = r.report_date.split('-');
        if (parts.length === 3) {
          const dateObj = new Date(parts[2], parts[0] - 1, parts[1]);
          displayDate = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        }
      }
      
      // Build content HTML
      let html = `
        <p><strong>Date:</strong> ${displayDate}</p>
        <p><strong>Unit:</strong> ${r.unit_name || 'N/A'}</p>
        <p><strong>Progress:</strong> ${r.progress_percentage || 0}%</p>
        <p><strong>Created By:</strong> ${r.created_by || 'N/A'}</p>
        <p><strong>Remarks:</strong> ${r.remarks || 'None'}</p>
        
        <div class="section-divider"></div>
        
        <h4>Work Done</h4>
        <p>${r.work_done || 'No description provided.'}</p>
        
        <div class="section-divider"></div>
        
        <h4>Materials Used</h4>
      `;
      
      if (r.materials_used && r.materials_used.length > 0) {
        html += '<table style="width:100%; font-size:13px; margin-top:10px;"><thead><tr><th style="text-align:left;">Material</th><th style="text-align:center;">Qty Used</th><th style="text-align:center;">Unit</th></tr></thead><tbody>';
        r.materials_used.forEach(m => {
          html += `<tr><td>${m.name}</td><td style="text-align:center;">${m.quantity_used}</td><td style="text-align:center;">${m.unit_of_measurement}</td></tr>`;
        });
        html += '</tbody></table>';
      } else {
        html += '<p style="color:#888;">No materials recorded for this report.</p>';
      }
      
      html += '<div class="section-divider"></div><h4>Proof Images</h4>';
      
      if (r.images && r.images.length > 0) {
        html += '<div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;">';
        r.images.forEach(img => {
          html += `<a href="report_images/${img.image_path}" target="_blank">
            <img src="report_images/${img.image_path}" style="width:100px; height:100px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb; cursor:pointer;">
          </a>`;
        });
        html += '</div>';
      } else {
        html += '<p style="color:#888;">No proof images uploaded.</p>';
      }
      
      document.getElementById('view-report-content').innerHTML = html;
      
      // Set edit button action
      document.getElementById('view-report-edit-btn').onclick = function() {
        toggleViewReportOverlay(false);
        openEditReportOverlay(reportId);
      };
    })
    .catch(err => {
      console.error(err);
      showToast('Error loading report data.', 'error');
      toggleViewReportOverlay(false);
    });
}

// ======================================================
// EDIT REPORT OVERLAY FUNCTIONS
// ======================================================
function toggleEditReportOverlay(show) {
  document.getElementById('editReportOverlay').style.display = show ? 'flex' : 'none';
  if (!show) {
    document.getElementById('editReportForm').reset();
    document.getElementById('edit-existing-materials').innerHTML = '<p>Loading...</p>';
    document.getElementById('edit-existing-images').innerHTML = '<p style="color:#6b7280;">Loading...</p>';
    document.getElementById('edit-materials-rows').innerHTML = '';
    document.getElementById('edit-report-error-box').style.display = 'none';
  }
}

function openEditReportOverlay(reportId) {
  toggleEditReportOverlay(true);
  
  fetch(`get_report.php?id=${reportId}`)
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
      document.getElementById('edit_report_date').value = r.report_date;
      document.getElementById('edit_progress_percentage').value = r.progress_percentage;
      document.getElementById('edit_work_done').value = r.work_done;
      document.getElementById('edit_remarks').value = r.remarks || '';
      
      // Display existing materials
      const matsContainer = document.getElementById('edit-existing-materials');
      if (r.materials_used && r.materials_used.length > 0) {
        let matsHtml = '<table style="width:100%; font-size:13px;"><thead><tr><th style="text-align:left;">Material</th><th style="text-align:center;">Qty Used</th><th style="text-align:center;">Unit</th></tr></thead><tbody>';
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
          imgsHtml += `<img src="report_images/${img.image_path}" style="width:80px; height:80px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb;">`;
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

// ======================================================
// ADD MATERIAL ROW FUNCTIONALITY
// ======================================================
document.getElementById('edit-add-material-row').addEventListener('click', function() {
  const container = document.getElementById('edit-materials-rows');
  const rowId = Date.now();
  
  let optionsHtml = '<option value="">-- Select Material --</option>';
  allMaterials.forEach(m => {
    optionsHtml += `<option value="${m.id}" data-unit="${m.unit_of_measurement}" data-remaining="${m.remaining_quantity}">${m.name} (${m.remaining_quantity} ${m.unit_of_measurement} left)</option>`;
  });
  
  const rowHtml = `
    <div class="material-row" id="mat-row-${rowId}">
      <select name="new_material_id[]" onchange="updateMaterialUnit(this, ${rowId})">${optionsHtml}</select>
      <input type="number" name="new_quantity_used[]" placeholder="Qty" min="1">
      <span class="unit-display" id="unit-${rowId}">—</span>
      <button type="button" class="btn-remove" onclick="document.getElementById('mat-row-${rowId}').remove()">✕</button>
    </div>
  `;
  
  container.insertAdjacentHTML('beforeend', rowHtml);
});

function updateMaterialUnit(selectEl, rowId) {
  const selected = selectEl.options[selectEl.selectedIndex];
  const unitSpan = document.getElementById('unit-' + rowId);
  if (selected && selected.dataset.unit) {
    unitSpan.textContent = selected.dataset.unit;
  } else {
    unitSpan.textContent = '—';
  }
}

// ======================================================
// FORM SUBMISSION VIA AJAX
// ======================================================
document.getElementById('editReportForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const form = this;
  const formData = new FormData(form);
  
  // Hide previous errors
  document.getElementById('edit-report-error-box').style.display = 'none';
  
  fetch('edit_report.php', {
    method: 'POST',
    body: formData,
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast(data.message || 'Report updated successfully!', 'success');
      toggleEditReportOverlay(false);
      // Reload the page to show updated data
      setTimeout(() => location.reload(), 1000);
    } else {
      // Show error
      const errorBox = document.getElementById('edit-report-error-box');
      const errorList = document.getElementById('edit-report-error-list');
      errorList.innerHTML = `<li>${data.message || 'An error occurred.'}</li>`;
      errorBox.style.display = 'block';
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Error submitting form.', 'error');
  });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
