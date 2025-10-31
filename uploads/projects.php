<?php
// ===============================================================
// uploads/projects.php — Final with Persistent Search + Sorting + Inline Overlays
// ===============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';
require_login();

$user_role = $_SESSION['user_role'] ?? 'constructor';
date_default_timezone_set('Asia/Manila');

// ✅ Add Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_project'])) {
    $project_name = trim($_POST['project_name']);
    $project_location = trim($_POST['project_location']);
    $units = intval($_POST['units']);
    $status = trim($_POST['status']);

    if (empty($project_name) || empty($project_location) || $units <= 0 || empty($status)) {
        echo "<script>alert('⚠️ Please fill in all required fields.');</script>";
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

            echo "<script>alert('✅ Project added successfully!'); window.location.href='projects.php';</script>";
        } else {
            echo "<script>alert('❌ Error adding project: " . addslashes($stmt->error) . "');</script>";
        }
    }
}

// ✅ Restore Project
if (isset($_GET['restore'])) {
    $id = intval($_GET['restore']);
    $stmt = $conn->prepare("UPDATE projects SET is_deleted = 0, deleted_at = NULL WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    echo "<script>alert('✅ Project restored successfully!'); window.location.href='projects.php?archived=1';</script>";
    exit;
}

// ✅ Permanent Delete
if (isset($_GET['delete_perm'])) {
    $id = intval($_GET['delete_perm']);
    $conn->begin_transaction();

    try {
        $conn->prepare("DELETE FROM project_units WHERE project_id = $id")->execute();
        $conn->prepare("DELETE FROM projects WHERE id = $id")->execute();
        $conn->commit();
        echo "<script>alert('🗑️ Project permanently deleted.'); window.location.href='projects.php?archived=1';</script>";
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('❌ Failed to permanently delete: " . addslashes($e->getMessage()) . "');</script>";
    }
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
:root {
  --brand-blue:#2563eb;
  --brand-blue-dark:#1d4ed8;
  --gray-100:#f3f4f6;
  --gray-200:#e5e7eb;
  --ink:#111827;
  --ok:#22c55e;
  --warn:#fbbf24;
  --danger:#dc2626;
}
body {background:var(--gray-100);font-family:'Inter',sans-serif;color:var(--ink);}
.main-container{padding:10px;}
.projects-header-container{
  background:#fff;border-radius:12px;padding:20px 24px;
  box-shadow:0 4px 12px rgba(0,0,0,.08);
  margin-bottom:22px;display:flex;justify-content:space-between;
  align-items:center;flex-wrap:wrap;gap:10px;
}
h2{margin:0;font-size:26px;color:#111;}
.btn-add,.btn-archive{background:var(--brand-blue);color:#fff;padding:10px 16px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;}
.btn-add:hover,.btn-archive:hover{background:var(--brand-blue-dark);}
.projects-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:20px;}
.project-card{
  background:#fff;border:1px solid var(--gray-200);
  border-radius:12px;padding:18px;box-shadow:0 2px 6px rgba(0,0,0,.08);
  transition:transform .15s ease;cursor:pointer;
}
.project-card:hover{transform:translateY(-2px);}
.project-title{font-size:18px;font-weight:700;color:#000;}
.status-badge{padding:4px 10px;border-radius:999px;font-size:12px;color:#fff;font-weight:700;}
.status-badge.Pending{background:var(--warn);}
.status-badge.Ongoing{background:var(--brand-blue);}
.status-badge.Completed{background:var(--ok);}
.btn-primary,.btn-cancel,.btn-restore,.btn-delete{
  border:none;border-radius:8px;padding:8px 14px;font-weight:600;font-size:14px;cursor:pointer;margin-right:6px;
}
.btn-primary{background:var(--brand-blue);color:#fff;}
.btn-primary:hover{background:var(--brand-blue-dark);}
.btn-cancel{background:#6b7280;color:#fff;}
.btn-cancel:hover{background:#4b5563;}
.btn-restore{background:var(--ok);color:#fff;}
.btn-restore:hover{background:#16a34a;}
.btn-delete{background:var(--danger);color:#fff;}
.btn-delete:hover{background:#b91c1c;}

/* ✅ Search + Sorting */
.search-bar input{
  padding:8px 12px;border:1px solid var(--gray-200);
  border-radius:8px;outline:none;
}
.sort-select{
  padding:8px 12px;border:1px solid var(--gray-200);
  border-radius:8px;cursor:pointer;
}

/* ✅ Overlay Styles */
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
input, select {
  padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:15px;background-color:#fff;
  transition:border-color 0.2s, box-shadow 0.2s;
}
input:focus, select:focus {
  outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,0.2);
}
.overlay-actions {display:flex;justify-content:flex-end;gap:10px;margin-top:25px;}
@keyframes zoomIn {from{opacity:0;transform:scale(0.95);}to{opacity:1;transform:scale(1);}}
@media (max-width:640px){.form-grid{grid-template-columns:1fr;}}
</style>

<div class="main-container">
  <div class="projects-header-container">
    <h2><?= $showArchived ? "Archived Projects" : "Projects" ?></h2>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <?php if (!$showArchived): ?>
        <div class="search-bar">
          <input type="text" id="searchInput" placeholder="Search project or location...">
        </div>
        <select id="sortSelect" class="sort-select">
          <option value="latest">Sort: Latest</option>
          <option value="oldest">Sort: Oldest</option>
          <option value="name">Sort: Name A–Z</option>
        </select>
        <button class="btn-add" onclick="toggleOverlay(true)">+ Add Project</button>
        <button class="btn-archive" onclick="window.location.href='projects.php?archived=1'">View Archived</button>
      <?php else: ?>
        <button class="btn-archive" onclick="window.location.href='projects.php'">Back to Active</button>
      <?php endif; ?>
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
          <div class="project-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div class="project-title"><?= htmlspecialchars($p['name']) ?></div>
            <span class="status-badge <?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars($p['status']) ?></span>
          </div>
          <div class="project-details">
            <p><strong>Location:</strong> <?= htmlspecialchars($p['location']) ?></p>
            <p><strong>Units:</strong> <?= $p['units'] ?></p>
            <p><strong>Created:</strong> <?= date('M d, Y', strtotime($p['created_at'])) ?></p>
          </div>

          <?php if ($user_role === 'admin'): ?>
          <div style="margin-top:10px;" onclick="event.stopPropagation();">
            <?php if (!$showArchived): ?>
              <button onclick="openEditModal(<?= $p['id'] ?>)" class="btn-primary">Edit</button>
              <a href="../modules/delete_project.php?id=<?= $p['id'] ?>" onclick="return confirm('Archive this project?')" class="btn-cancel">Archive</a>
            <?php else: ?>
              <a href="projects.php?restore=<?= $p['id'] ?>" class="btn-restore" onclick="return confirm('Restore this project?')">Restore</a>
              <a href="projects.php?delete_perm=<?= $p['id'] ?>" class="btn-delete" onclick="return confirm('⚠️ Permanently delete this project?')">Delete</a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p style="color:#666;">No <?= $showArchived ? 'archived' : 'active' ?> projects found.</p>
    <?php endif; ?>
  </div>
</div>

<!-- ✅ ADD PROJECT OVERLAY -->
<div class="overlay" id="addOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleOverlay(false)">✕</button>
    <h3>Add New Project</h3>
    <form method="POST" action="">
      <div class="form-grid">
        <div class="form-group"><label>Project Name</label><input type="text" name="project_name" required></div>
        <div class="form-group"><label>Location</label><input type="text" name="project_location" required></div>
        <div class="form-group"><label>Units</label><input type="number" name="units" min="1" required></div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" required>
            <option value="Pending">Pending</option>
            <option value="Ongoing">Ongoing</option>
            <option value="Completed">Completed</option>
          </select>
        </div>
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

// ✅ Fetch project data for edit
function openEditModal(id){
  fetch(`../modules/get_project.php?id=${id}`)
  .then(res=>res.json())
  .then(data=>{
    if(!data || !data.id){alert('❌ Project not found');return;}
    document.getElementById('edit_project_id').value=data.id;
    document.getElementById('edit_project_name').value=data.name;
    document.getElementById('edit_project_location').value=data.location;
    document.getElementById('edit_units').value=data.units;
    document.getElementById('edit_status').value=data.status;
    toggleEditOverlay(true);
  })
  .catch(()=>alert('Error fetching project data.'));
}

// ✅ Update Project
document.getElementById('editForm').addEventListener('submit',e=>{
  e.preventDefault();
  const formData=new FormData(e.target);
  fetch('../modules/edit_project.php',{method:'POST',body:formData})
  .then(r=>r.text())
  .then(()=>{alert('✅ Project updated successfully!');location.reload();})
  .catch(()=>alert('❌ Update failed.'));
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
