<?php
// ===============================================================
// uploads/projects.php — Final with Persistent Search + Sorting + Inline Overlays
// ===============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Start session if not started (for require_login check before header)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check login before any processing
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_role = $_SESSION['user_role'] ?? 'admin';
date_default_timezone_set('Asia/Manila');

// ============================================================
// PROCESS ALL REDIRECTS BEFORE INCLUDING HEADER (no HTML output yet)
// ============================================================

// ✅ Restore Project
if (isset($_GET['restore'])) {
    $id = intval($_GET['restore']);
    
    // Get project name for logging
    $proj_stmt = $conn->prepare("SELECT name FROM projects WHERE id = ?");
    $proj_stmt->bind_param("i", $id);
    $proj_stmt->execute();
    $proj_name = $proj_stmt->get_result()->fetch_assoc()['name'] ?? 'Unknown';
    $proj_stmt->close();
    
    // When restoring, set a default non-archived status, e.g., 'Ongoing' (adjust if needed)
    $stmt = $conn->prepare("UPDATE projects SET is_deleted = 0, deleted_at = NULL, status = 'Ongoing' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    log_activity($conn, 'RESTORE_PROJECT', "Restored project: $proj_name (ID: $id) from Archive");
    header("Location: projects.php?status=success&message=" . urlencode("Project restored successfully with status: Ongoing!"));
    exit;
}

// ✅ Permanent Delete
if (isset($_GET['delete_perm'])) {
    $id = intval($_GET['delete_perm']);
    
    // Get project name for logging
    $proj_stmt = $conn->prepare("SELECT name FROM projects WHERE id = ?");
    $proj_stmt->bind_param("i", $id);
    $proj_stmt->execute();
    $proj_name = $proj_stmt->get_result()->fetch_assoc()['name'] ?? 'Unknown';
    $proj_stmt->close();
    
    $conn->begin_transaction();

    try {
        $conn->prepare("DELETE FROM project_units WHERE project_id = $id")->execute();
        $conn->prepare("DELETE FROM projects WHERE id = $id")->execute();
        $conn->commit();
        log_activity($conn, 'DELETE_PROJECT', "Permanently deleted project: $proj_name (ID: $id)");
        header("Location: projects.php?archived=1&status=success&message=" . urlencode("Project permanently deleted."));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: projects.php?archived=1&status=error&message=" . urlencode("Failed to permanently delete: " . $e->getMessage()));
        exit;
    }
}

// ✅ Add Project - MODIFIED: Status is now automatically set to 'Pending'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_project'])) {
    $project_name = trim($_POST['project_name']);
    $project_location = trim($_POST['project_location']);
    $units = intval($_POST['units']);
    $status = 'Pending'; // ✅ AUTOMATED: Hardcode initial status to 'Pending'

    if (empty($project_name) || empty($project_location) || $units <= 0) {
        // Store error in session to display after redirect
        $_SESSION['toast_message'] = 'Please fill in all required fields (Project Name, Location, Units).';
        $_SESSION['toast_type'] = 'warning';
        header("Location: projects.php");
        exit;
    } else {
        $stmt = $conn->prepare("INSERT INTO projects (name, location, units, status, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssis", $project_name, $project_location, $units, $status);

        if ($stmt->execute()) {
            $project_id = $stmt->insert_id;
            $stmt->close();

            $unit_stmt = $conn->prepare("INSERT INTO project_units (project_id, name, description, progress, created_at) VALUES (?, ?, '', 0, NOW())");
            for ($i = 1; $i <= $units; $i++) {
                $unit_name = "Unit " . $i;
                $unit_stmt->bind_param("is", $project_id, $unit_name);
                $unit_stmt->execute();
            }
            $unit_stmt->close();

            // Log the activity
            log_activity($conn, 'ADD_PROJECT', "Created project: $project_name (Location: $project_location, Units: $units, ID: $project_id)");

            header("Location: projects.php?status=success&message=" . urlencode("Project added successfully with status: Pending!"));
            exit;
        } else {
            $_SESSION['toast_message'] = 'Error adding project: ' . $stmt->error;
            $_SESSION['toast_type'] = 'error';
            header("Location: projects.php");
            exit;
        }
    }
}

// ✅ NEW: Confirm Project (Pending -> Ongoing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_project'])) {
    $id = intval($_POST['project_id']);
    
    // Get project name for logging
    $proj_stmt = $conn->prepare("SELECT name FROM projects WHERE id = ?");
    $proj_stmt->bind_param("i", $id);
    $proj_stmt->execute();
    $proj_name = $proj_stmt->get_result()->fetch_assoc()['name'] ?? 'Unknown';
    $proj_stmt->close();
    
    // Only update if current status is 'Pending'
    $stmt = $conn->prepare("UPDATE projects SET status = 'Ongoing' WHERE id = ? AND status = 'Pending'");
    $stmt->bind_param("i", $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        log_activity($conn, 'CONFIRM_PROJECT', "Confirmed project: $proj_name (ID: $id) - Status changed to Ongoing");
        header("Location: projects.php?status=success&message=" . urlencode("Project confirmed and status set to Ongoing!"));
    } else {
        header("Location: projects.php?status=error&message=" . urlencode("Error confirming project or status was not Pending."));
    }
    $stmt->close();
    exit;
}

// ✅ NEW: Cancel Project (Pending -> Cancelled/Archive)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_project'])) {
    $id = intval($_POST['project_id']);
    
    // Get project name for logging
    $proj_stmt = $conn->prepare("SELECT name FROM projects WHERE id = ?");
    $proj_stmt->bind_param("i", $id);
    $proj_stmt->execute();
    $proj_name = $proj_stmt->get_result()->fetch_assoc()['name'] ?? 'Unknown';
    $proj_stmt->close();
    
    // Archive the project AND set status to 'Cancelled'
    $stmt = $conn->prepare("UPDATE projects SET is_deleted = 1, deleted_at = NOW(), status = 'Cancelled' WHERE id = ? AND status = 'Pending'");
    $stmt->bind_param("i", $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        log_activity($conn, 'CANCEL_PROJECT', "Cancelled project: $proj_name (ID: $id) - Moved to Archive");
        header("Location: projects.php?status=success&message=" . urlencode("Project cancelled and moved to Archive."));
    } else {
        header("Location: projects.php?status=error&message=" . urlencode("Error cancelling project or status was not Pending."));
    }
    $stmt->close();
    exit;
}

// ============================================================
// NOW INCLUDE HEADER (HTML output starts here)
// ============================================================
require_once __DIR__ . '/../includes/header.php';

// Check for session-based toast messages
if (isset($_SESSION['toast_message'])) {
    $toast_msg = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'] ?? 'info';
    unset($_SESSION['toast_message'], $_SESSION['toast_type']);
    echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast(" . json_encode($toast_msg) . ", '$toast_type'); });</script>";
}

// ✅ View Mode
$showArchived = isset($_GET['archived']);
if ($showArchived) {
    $stmt = $conn->prepare("SELECT id, name, location, units, progress, status, created_at, deleted_at 
                            FROM projects WHERE is_deleted = 1 ORDER BY deleted_at DESC");
} else {
    $stmt = $conn->prepare("SELECT id, name, location, units, progress, status, created_at 
                            FROM projects WHERE is_deleted = 0 ORDER BY id DESC");
}
$stmt->execute();
$projects = $stmt->get_result();
?>

<style>
/* --- STYLING REMAINS UNCHANGED AS REQUESTED --- */
/* You may want to add a .status.cancelled style here if the status changes to 'Cancelled' */
.status.cancelled { background-color: #ef4444; } /* Optional: Added for 'Cancelled' status */


/* === Variables (From old projects.php for button colors) === */
:root {
  --brand-blue:#2563eb;
  --brand-blue-dark:#1d4ed8;
  --ok:#16a34a; /* Adjusted from #22c55e to match reports.php style */
  --warn:#f59e0b; /* Adjusted from #fbbf24 to match reports.php style */
  --danger:#dc2626;
  --gray-100:#f8fafc; /* Matches reports.php background */
}

/* === Layout Wrapper === */
.content-wrapper {
  padding: 20px;
  background: var(--gray-100);
  font-family:'Inter',sans-serif; /* Added font */
  color:#111827; /* Added text color */
}

/* === Header Container === */
.header-container {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding:20px 24px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  margin-bottom: 25px;
}

/* === Projects Header Row === */
.projects-header { /* Renaming reports-header to projects-header for clarity */
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.projects-header h2 {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

/* === Controls (Search + Sort + Buttons) === */
.controls {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap; /* Added to handle button wrapping */
}

.controls input,
.controls select { /* Combined search-bar input and sort-select */
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  background-color: #fff;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.controls input {
    width: 230px;
}
.controls input:focus,
.controls select:focus {
  outline: none;
  border-color: var(--brand-blue);
  box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
}

/* --- BUTTON STYLES (Kept from old projects.php but using new class names) --- */
.btn-add, .btn-archive {
    background: var(--brand-blue);
    color: #fff;
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s;
}
.btn-add:hover, .btn-archive:hover {
    background: var(--brand-blue-dark);
}

.btn-primary,.btn-cancel,.btn-restore,.btn-delete{
  border:none;border-radius:8px;padding:8px 14px;font-weight:600;font-size:14px;cursor:pointer;margin-right:6px;
  transition: background-color 0.2s;
}
.btn-primary{background:var(--brand-blue);color:#fff;}
.btn-primary:hover{background:var(--brand-blue-dark);}
.btn-cancel{background:#6b7280;color:#fff;}
.btn-cancel:hover{background:#4b5563;}
.btn-restore{background:var(--ok);color:#fff;}
.btn-restore:hover{background:#16a34a;}
.btn-delete{background:var(--danger);color:#fff;}
.btn-delete:hover{background:#b91c1c;}


/* === Project Grid === */
.projects-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 20px;
}

/* === Project Card === */
.project-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 18px 20px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  transition: all 0.2s ease;
  cursor: pointer;
}
.project-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 5px 12px rgba(0, 0, 0, 0.1);
  border-color: var(--brand-blue);
}

/* === Project Header === */
.project-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 8px;
}
.project-header h3 { /* Using h3 for project title in card */
  margin: 0;
  font-size: 18px;
  color: #1f2937;
  flex: 1;
}

/* === Status Badge === */
.status { /* Reusing reports.php status class */
  padding: 4px 10px;
  border-radius: 6px; /* Slightly adjusted from 999px */
  font-size: 13px; /* Slightly adjusted from 12px */
  font-weight: 600;
  text-transform: capitalize;
  color: #fff;
  white-space: nowrap;
  flex-shrink: 0;
}
/* Re-map status classes for projects.php statuses (e.g., Pending, Ongoing, Completed) */
.status.pending { background-color: var(--warn); }
.status.ongoing { background-color: var(--brand-blue); }
.status.completed { background-color: var(--ok); }
.status.archived, .status.cancelled { background-color: #6b7280; } /* Added for archived/cancelled view */

/* === Empty State === */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  grid-column: 1 / -1;
}
.empty-state .no-data {
  color: #4b5563;
  font-size: 16px;
  margin: 0;
}


/* === Card Text === */
.project-card p {
  font-size: 14px;
  color: #374151;
  margin: 4px 0;
}

/* === No Data === */
.no-data {
  text-align: center;
  color: #6b7280;
  font-style: italic;
}

/* --- OVERLAY STYLES (Retained from old projects.php) --- */
.overlay {
  position: fixed;inset: 0;background: rgba(0,0,0,0.6);
  display: none;align-items: center;justify-content: center;z-index: 9999;
}
.overlay-card {
  background: #fff;border-radius: 12px;padding: 30px 36px;
  width: 100%;max-width: 600px;box-shadow: 0 10px 25px rgba(0,0,0,.3);
  position: relative;animation: zoomIn .2s ease both;
}
.close-btn {position: absolute;top: 15px;right: 18px;background: none;border: none;font-size: 22px;color: #6b7280;cursor: pointer;}
.close-btn:hover { color: #111827; }
.form-grid {display:grid;grid-template-columns:1fr 1fr;gap:18px 22px;}
.form-group {display:flex;flex-direction:column;gap:6px;}
label {font-weight:600;color:#374151;font-size:14px;}
/* Input/Select styling already handled in controls section */
.overlay-actions {display:flex;justify-content:flex-end;gap:10px;margin-top:25px;}
@keyframes zoomIn {from{opacity:0;transform:scale(0.95);}to{opacity:1;transform:scale(1);}}
@media (max-width:640px){.form-grid{grid-template-columns:1fr;}}

</style>

<div class="content-wrapper">
  
  <!-- 🔍 Projects Header Container -->
  <div class="header-container">
    <div class="projects-header">
      <h2><?= $showArchived ? "Archived Projects" : "Projects" ?></h2>

      <div class="controls">
        <?php if (!$showArchived): ?>
          <input type="text" id="searchInput" placeholder="Search project or location...">
          <select id="sortSelect" class="sort-select">
            <option value="latest">Sort: Latest</option>
            <option value="oldest">Sort: Oldest</option>
            <option value="name">Sort: Name A–Z</option>
          </select>
          <?php if (is_admin()): ?>
          <button class="btn-add" onclick="toggleOverlay(true)">+ Add Project</button>
          <?php endif; ?>
          <button class="btn-archive" onclick="window.location.href='projects.php?archived=1'">View Archived</button>
        <?php else: ?>
          <button class="btn-archive" onclick="window.location.href='projects.php'">Back to Active</button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="projects-grid" id="projectGrid">
    <?php if ($projects->num_rows > 0): ?>
      <?php while ($p = $projects->fetch_assoc()): ?>
        <div class="project-card" 
             onclick="window.location.href='../modules/view_project.php?id=<?= $p['id'] ?>'"
             data-name="<?= strtolower($p['name']) ?>" 
             data-location="<?= strtolower($p['location']) ?>" 
             data-created="<?= strtotime($p['created_at']) ?>">
          
          <div class="project-header">
            <h3><?= htmlspecialchars($p['name']) ?></h3>
            <span class="status <?= strtolower($p['status']) ?> <?= $showArchived ? 'archived' : '' ?>">
              <?= htmlspecialchars($p['status']) ?>
            </span>
          </div>
          <div class="project-details">
            <p><strong>Location:</strong> <?= htmlspecialchars($p['location']) ?></p>
            <p><strong>Units:</strong> <?= $p['units'] ?></p>
            <p><strong>Created:</strong> <?= date('M d, Y', strtotime($p['created_at'])) ?></p>
          </div>

          <?php if (is_admin()): ?>
          <div style="margin-top:10px;" onclick="event.stopPropagation();">
            <?php if (!$showArchived): // Active Projects View ?>
              
              <?php if ($p['status'] === 'Pending'): // ✅ NEW LOGIC: Confirm/Cancel/Edit for Pending status ?>
                <button onclick="confirmStatusChange(<?= $p['id'] ?>, 'confirm')" class="btn-primary">Confirm</button>
                <button onclick="confirmStatusChange(<?= $p['id'] ?>, 'cancel')" class="btn-cancel">Cancel</button>
                <button onclick="openEditModal(<?= $p['id'] ?>)" class="btn-primary">Edit</button>

              <?php else: // Original Logic for Ongoing/Completed ?>
                <button onclick="openEditModal(<?= $p['id'] ?>)" class="btn-primary">Edit</button>
                <a href="../modules/delete_project.php?id=<?= $p['id'] ?>" onclick="return confirm('Archive this project?')" class="btn-cancel">Archive</a>
              <?php endif; ?>

            <?php else: // Archived Projects View ?>
              <a href="projects.php?restore=<?= $p['id'] ?>" class="btn-restore" onclick="return confirm('Restore this project?')">Restore</a>
              <a href="projects.php?delete_perm=<?= $p['id'] ?>" class="btn-delete" onclick="return confirm('⚠️ Permanently delete this project?')">Delete</a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="empty-state">
        <i class="fa-solid fa-folder-open" style="font-size: 48px; color: #9ca3af; margin-bottom: 15px;"></i>
        <p class="no-data">No <?= $showArchived ? 'archived' : 'active' ?> projects found.</p>
        <?php if ($showArchived): ?>
        <p style="color: #6b7280; margin-top: 10px;">Archived projects will appear here when you archive them from the active projects list.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ✅ ADD PROJECT OVERLAY - MODIFIED: Removed Status dropdown -->
<div class="overlay" id="addOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleOverlay(false)">✕</button>
    <h3>Add New Project</h3>
    <form method="POST" action="">
      <div class="form-grid">
        <div class="form-group"><label>Project Name</label><input type="text" name="project_name" required></div>
        <div class="form-group"><label>Location</label><input type="text" name="project_location" required></div>
        <div class="form-group"><label>Units</label><input type="number" name="units" min="1" required></div>
        <!-- Status field REMOVED: Status is now automatically set to 'Pending' in PHP -->
      </div>
      <div class="overlay-actions">
        <button type="button" class="btn-cancel" onclick="toggleOverlay(false)">Cancel</button>
        <button type="submit" name="save_project" class="btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ✅ EDIT PROJECT OVERLAY -->
<div class="overlay" id="editOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleEditOverlay(false)">✕</button>
    <h3>Edit Project</h3>
    <form id="editForm">
      <input type="hidden" name="project_id" id="edit_project_id">
      <div class="form-grid">
        <div class="form-group"><label>Project Name</label><input type="text" id="edit_project_name" name="project_name" required></div>
        <div class="form-group"><label>Location</label><input type="text" id="edit_project_location" name="project_location" required></div>
        <div class="form-group"><label>Units</label><input type="number" id="edit_units" name="units" min="1" required></div>
        <div class="form-group">
          <label>Status</label>
          <select id="edit_status" name="status" required>
            <option value="Pending">Pending</option>
            <option value="Ongoing">Ongoing</option>
            <option value="Completed">Completed</option>
            <option value="Cancelled">Cancelled</option> <!-- Added Cancelled for completeness -->
          </select>
        </div>
      </div>
      <div class="overlay-actions">
        <button type="button" class="btn-cancel" onclick="toggleEditOverlay(false)">Cancel</button>
        <button type="submit" class="btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleOverlay(show){document.getElementById('addOverlay').style.display=show?'flex':'none';}
function toggleEditOverlay(show){document.getElementById('editOverlay').style.display=show?'flex':'none';}

// ✅ NEW: Status change confirmation function
function confirmStatusChange(id, action) {
    let message = (action === 'confirm') 
        ? 'Are you sure you want to CONFIRM this project? Its status will change to Ongoing.' 
        : 'Are you sure you want to CANCEL this project? It will be moved to Archive.';

    if (confirm(message)) {
        // Create a hidden form to submit the request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'projects.php'; 

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'project_id';
        idInput.value = id;
        form.appendChild(idInput);

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = (action === 'confirm') ? 'confirm_project' : 'cancel_project';
        actionInput.value = '1'; // A value to trigger the PHP POST logic
        form.appendChild(actionInput);

        document.body.appendChild(form);
        form.submit();
    }
}

// ✅ Fetch project data for edit
function openEditModal(id){
  fetch(`../modules/get_project.php?id=${id}`)
  .then(res=>res.json())
  .then(data=>{
    if(!data || !data.id){showToast('Project not found', 'error');return;}
    document.getElementById('edit_project_id').value=data.id;
    document.getElementById('edit_project_name').value=data.name;
    document.getElementById('edit_project_location').value=data.location;
    document.getElementById('edit_units').value=data.units;
    document.getElementById('edit_status').value=data.status;
    toggleEditOverlay(true);
  })
  .catch(()=>showToast('Error fetching project data.', 'error'));
}

// ✅ Update Project
document.getElementById('editForm').addEventListener('submit',e=>{
  e.preventDefault();
  const formData=new FormData(e.target);
  fetch('../modules/edit_project.php',{method:'POST',body:formData})
  .then(r=>r.text())
  .then(()=>{showToast('Project updated successfully!', 'success');setTimeout(()=>location.reload(), 1000);})
  .catch(()=>showToast('Update failed.', 'error'));
});

// ✅ Persist Search + Sort
window.addEventListener('DOMContentLoaded', ()=>{
  const searchInput=document.getElementById('searchInput');
  const sortSelect=document.getElementById('sortSelect');
  const cards=[...document.querySelectorAll('.project-card')];
  const grid=document.getElementById('projectGrid');

  const savedSearch=localStorage.getItem('projectSearch')||'';
  const savedSort=localStorage.getItem('projectSort')||'latest';
  searchInput.value=savedSearch;
  sortSelect.value=savedSort;

  applyFilters();

  searchInput.addEventListener('keyup', ()=>{
    localStorage.setItem('projectSearch', searchInput.value);
    applyFilters();
  });

  sortSelect.addEventListener('change', ()=>{
    localStorage.setItem('projectSort', sortSelect.value);
    applyFilters();
  });

  function applyFilters(){
    const q=searchInput.value.toLowerCase();
    const sort=sortSelect.value;

    let visibleCards=cards.filter(c=>{
      const name=c.dataset.name, loc=c.dataset.location;
      c.style.display=(name.includes(q)||loc.includes(q))?'block':'none';
      return c.style.display==='block';
    });

    if(sort==='latest') visibleCards.sort((a,b)=>b.dataset.created - a.dataset.created);
    else if(sort==='oldest') visibleCards.sort((a,b)=>a.dataset.created - b.dataset.created);
    else if(sort==='name') visibleCards.sort((a,b)=>a.dataset.name.localeCompare(b.dataset.name));

    grid.innerHTML='';
    visibleCards.forEach(c=>grid.appendChild(c));
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>