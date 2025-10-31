<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if (!isset($_GET['project_id']) || !isset($_GET['unit_id'])) {
    die('<h3 style="color:red;">Invalid parameters provided.</h3>');
}

$project_id = (int)$_GET['project_id'];
$unit_id = (int)$_GET['unit_id'];

$project = $conn->query("SELECT name FROM projects WHERE id = $project_id")->fetch_assoc();
$unit = $conn->query("SELECT name FROM project_units WHERE id = $unit_id")->fetch_assoc();

include '../includes/header.php';
?>

<div class="main-content-wrapper">
  <div class="project-header-card">
    <div class="header-row">
      <h2><?= htmlspecialchars($unit['name']); ?> — <?= htmlspecialchars($project['name']); ?></h2>
      <a href="../modules/view_project.php?id=<?= $project_id; ?>" class="btn-back">← Back to Project</a>
    </div>
    <p><strong>Unit ID:</strong> <?= $unit_id; ?></p>
  </div>

  <!-- Unified Checklist Container -->
  <div class="checklist-container">
    <div class="checklist-header">
      <h3>Checklist Items</h3>
      <button class="btn btn-primary" onclick="toggleAddOverlay(true)">+ Add New Item</button>
    </div>

    <!-- Checklist Table -->
    <div id="checklistTableContainer">
      <?php
      $checklists = $conn->query("SELECT * FROM project_checklists WHERE project_id = $project_id AND unit_id = $unit_id ORDER BY id ASC");
      if ($checklists->num_rows > 0): ?>
        <div class="checklist-table">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Description</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 1; while($item = $checklists->fetch_assoc()): ?>
                <tr>
                  <td><?= $i++; ?></td>
                  <td><?= htmlspecialchars($item['item_description']); ?></td>
                  <td>
                    <?php if ($item['is_completed']): ?>
                      <span class="status completed">Completed</span>
                    <?php else: ?>
                      <span class="status pending">Pending</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($item['is_completed']): ?>
                      <button class="btn-cancel action-btn" data-action="uncheck" data-id="<?= $item['id']; ?>">Uncheck</button>
                    <?php else: ?>
                      <button class="btn-primary action-btn" data-action="check" data-id="<?= $item['id']; ?>">Mark Complete</button>
                    <?php endif; ?>
                    <button class="btn-delete delete-btn" data-id="<?= $item['id']; ?>">Delete</button>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:#888;">No checklist items yet for this unit.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ✅ ADD ITEM OVERLAY -->
<div class="overlay" id="addItemOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleAddOverlay(false)">✕</button>
    <h3 class="overlay-title">Add Checklist Item</h3>
    <form id="addItemForm" method="POST" action="process_add_checklist_item.php">
      <input type="hidden" name="project_id" value="<?= $project_id; ?>">
      <input type="hidden" name="unit_id" value="<?= $unit_id; ?>">
      <input type="hidden" name="apply_mode" value="single">

      <div class="form-group">
        <label for="desc">Checklist Description:</label>
        <input type="text" id="desc" name="item_description" placeholder="Enter checklist description" required>
      </div>

      <div class="form-actions">
        <button type="button" class="btn-cancel" onclick="toggleAddOverlay(false)">Cancel</button>
        <button type="submit" class="btn-primary">Add Item</button>
      </div>
    </form>
  </div>
</div>

<style>
.main-content-wrapper{padding:20px;}
.project-header-card{background:#fff;padding:20px;border-radius:8px;margin-bottom:20px;box-shadow:0 4px 10px rgba(0,0,0,0.08);}
.header-row{display:flex;justify-content:space-between;align-items:center;}
.btn-back{background:#374151;color:#fff;text-decoration:none;padding:8px 14px;border-radius:6px;}
.btn-back:hover{background:#111827;}

/* Unified Checklist Container */
.checklist-container {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.06);
  padding: 20px;
  margin-top: 15px;
}

.checklist-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}
.checklist-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}

/* Table Styles */
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;}
.status.completed{color:#22c55e;font-weight:600;}
.status.pending{color:#f59e0b;font-weight:600;}

/* Buttons */
.btn,
.btn-primary,
.btn-cancel,
.btn-delete {
  border: none;
  outline: none;
  box-shadow: none;
  cursor: pointer;
  font-weight: 600;
  border-radius: 6px;
  transition: background 0.2s ease, transform 0.1s ease;
}
.btn-primary {
  background: #2563eb;
  color: #fff;
  padding: 8px 14px;
}
.btn-primary:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
}
.btn-cancel {
  background: #6b7280;
  color: #fff;
  padding: 8px 14px;
}
.btn-cancel:hover {
  background: #4b5563;
  transform: translateY(-1px);
}
.btn-delete {
  background: #dc2626;
  color: #fff;
  padding: 8px 14px;
}
.btn-delete:hover {
  background: #b91c1c;
  transform: translateY(-1px);
}
button:focus,
.btn:focus,
.btn-primary:focus,
.btn-cancel:focus,
.btn-delete:focus {
  outline: none !important;
  box-shadow: none !important;
}

/* Overlay */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,0.55);display:none;align-items:center;justify-content:center;z-index:10000;}
.overlay-card{background:#fff;border-radius:12px;width:100%;max-width:500px;padding:28px 34px 32px;box-shadow:0 12px 30px rgba(0,0,0,0.25);position:relative;animation:fadeIn 0.25s ease;}
.overlay-title{font-size:20px;font-weight:700;color:#111827;margin-bottom:20px;}
.close-btn{position:absolute;top:12px;right:14px;background:none;border:none;font-size:22px;color:#6b7280;cursor:pointer;}
.close-btn:hover{color:#111827;}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:14px;}
label{font-weight:600;color:#374151;font-size:14px;}
input[type="text"]{padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:15px;width:100%;background:#fff;transition:border-color 0.2s ease, box-shadow 0.2s ease;}
input[type="text"]:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,0.2);}
.form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:10px;}
@keyframes fadeIn{from{opacity:0;transform:scale(0.96);}to{opacity:1;transform:scale(1);}}
</style>

<script>
function toggleAddOverlay(show){
  document.getElementById('addItemOverlay').style.display = show ? 'flex' : 'none';
}

// ✅ Reload table
function reloadChecklist() {
  fetch(`view_checklist.php?project_id=<?= $project_id; ?>&unit_id=<?= $unit_id; ?> #checklistTableContainer`)
    .then(res => res.text())
    .then(html => {
      const parser = new DOMParser();
      const newDoc = parser.parseFromString(html, 'text/html');
      document.querySelector('#checklistTableContainer').innerHTML = newDoc.querySelector('#checklistTableContainer').innerHTML;
      attachEventListeners();
    });
}

// ✅ Add new item
document.getElementById('addItemForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch('process_add_checklist_item.php', { method: 'POST', body: formData })
    .then(() => {
      toggleAddOverlay(false);
      this.reset();
      reloadChecklist();
    })
    .catch(err => console.error('Error:', err));
});

// ✅ Mark Complete / Uncheck / Delete
function attachEventListeners() {
  document.querySelectorAll('.action-btn').forEach(btn => {
    btn.onclick = () => {
      const id = btn.dataset.id;
      const action = btn.dataset.action;
      fetch(`process_toggle_checklist_item.php?id=${id}&project_id=<?= $project_id; ?>&unit_id=<?= $unit_id; ?>&action=${action}`)
        .then(() => reloadChecklist());
    };
  });

  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.onclick = () => {
      const id = btn.dataset.id;
      if (confirm('Delete this checklist item?')) {
        // ✅ fixed to use the correct PHP file
        fetch(`delete_checklist_item.php?id=${id}&project_id=<?= $project_id; ?>&unit_id=<?= $unit_id; ?>`)
          .then(() => reloadChecklist());
      }
    };
  });
}

// ✅ Run after page load
attachEventListeners();
</script>

<?php include '../includes/footer.php'; ?>
