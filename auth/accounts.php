<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

// Only admins can access this page
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

include '../includes/header.php';

// Helper to determine role color class
function get_role_class($role) {
    if ($role === 'admin') return 'role-admin';
    return 'role-constructor';
}

// Fetch users for display
$users = $conn->query("SELECT * FROM users ORDER BY id ASC");
?>

<div class="content-wrapper">
  <div class="content-container">
    <div class="page-header">
      <h2>User Accounts</h2>
      <!-- Custom Button Style -->
      <button class="btn-add" onclick="toggleAddOverlay(true)">
        <i class="fa-solid fa-user-plus"></i> Add New User
      </button>
    </div>

    <!-- Alert Container -->
    <div id="alert-container"></div>

    <div class="card">
      <div class="table-responsive">
        <table class="data-table" id="usersTable">
          <thead>
            <tr>
              <th style="width: 10%">#</th>
              <th>Username</th>
              <th>Role</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if ($users && $users->num_rows > 0):
              while ($row = $users->fetch_assoc()):
            ?>
              <tr data-id="<?= $row['id']; ?>">
                <td><?= $row['id']; ?></td>
                <td><?= htmlspecialchars($row['username']); ?></td>
                <td>
                  <span class="role-badge <?= get_role_class($row['role']); ?>">
                    <?= htmlspecialchars(ucfirst($row['role'])); ?>
                  </span>
                </td>
                <td class="actions">
                  <button class="btn-icon btn-edit edit-btn" data-id="<?= $row['id']; ?>" title="Edit">
                    <i class="fa-solid fa-pencil-square"></i>
                  </button>
                  <button class="btn-icon btn-delete delete-btn" data-id="<?= $row['id']; ?>" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="4" class="empty">No users found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ===================== ADD USER OVERLAY (Guaranteed Custom Overlay) ===================== -->
<div class="overlay" id="addUserModal" style="display: none;">
  <div class="overlay-card">
    
    <div class="overlay-header bg-primary">
      <h5 class="overlay-title"><i class="fa-solid fa-user-plus"></i> Add User</h5>
      <button type="button" class="close-btn" onclick="toggleAddOverlay(false)">✕</button>
    </div>
    
    <div class="overlay-body">
      <form id="addUserForm">
        <!-- Form Fields with adjusted margins/padding -->
        <div class="form-group mb-4">
          <label class="form-label">Username</label>
          <input type="text" class="form-control" name="username" required>
        </div>
        <div class="form-group mb-4">
          <label class="form-label">Password</label>
          <input type="password" class="form-control" name="password" required>
        </div>
        <div class="form-group mb-4">
          <label class="form-label">Role</label>
          <select name="role" class="form-select" required>
            <option value="admin">Admin</option>
            <option value="constructor">Constructor</option>
          </select>
        </div>
        <div class="form-actions-modal">
          <button type="button" class="btn-cancel-modal" onclick="toggleAddOverlay(false)">Cancel</button>
          <button type="submit" class="btn-primary-modal">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== EDIT USER OVERLAY (Guaranteed Custom Overlay) ===================== -->
<div class="overlay" id="editUserModal" style="display: none;">
  <div class="overlay-card">
    
    <div class="overlay-header bg-warning">
      <h5 class="overlay-title"><i class="fa-solid fa-pencil-square"></i> Edit User</h5>
      <button type="button" class="close-btn" onclick="toggleEditOverlay(false)">✕</button>
    </div>
    
    <div class="overlay-body">
      <form id="editUserForm">
        <input type="hidden" name="id" id="editUserId">
        <div class="form-group mb-4">
          <label class="form-label">Username</label>
          <input type="text" class="form-control" name="username" id="editUsername" required>
        </div>
        <div class="form-group mb-4">
          <label class="form-label">New Password</label>
          <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current">
          <!-- FIX: Helper text wrapped in small tag -->
          <small class="form-text-helper">Leave blank to keep current password.</small>
        </div>
        <div class="form-group mb-4">
          <label class="form-label">Role</label>
          <select name="role" id="editRole" class="form-select" required>
            <option value="admin">Admin</option>
            <option value="constructor">Constructor</option>
          </select>
        </div>
        <div class="form-actions-modal">
          <button type="button" class="btn-cancel-modal" onclick="toggleEditOverlay(false)">Cancel</button>
          <button type="submit" class="btn-primary-modal btn-warning-submit">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Overlay Toast Notification -->
<div id="overlay-toast" class="overlay-toast"></div>

<script>
// =============== CUSTOM OVERLAY FUNCTIONS (DIRECT CONTROL) ===============
function toggleAddOverlay(show) {
    document.getElementById('addUserModal').style.display = show ? 'flex' : 'none';
    const modalInstance = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
    if (modalInstance) modalInstance.dispose();
    if (show) document.getElementById('addUserForm').reset();
}

function toggleEditOverlay(show) {
    document.getElementById('editUserModal').style.display = show ? 'flex' : 'none';
    const modalInstance = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
    if (modalInstance) modalInstance.dispose();
    if (show) document.getElementById('editUserForm').reset();
}
// =============== END CUSTOM OVERLAY FUNCTIONS ===============


const processURL = "process_user.php";

// =============== ADD USER ===============
document.getElementById('addUserForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('action', 'add');

  fetch(processURL, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(d => handleResponse(d, 'add'))
    .catch(err => showError(err));
});

// =============== EDIT USER POPUP ===============
document.querySelectorAll('.edit-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    fetch(`${processURL}?action=get&id=${id}`)
      .then(r => r.json())
      .then(user => {
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUsername').value = user.username;
        document.getElementById('editRole').value = user.role;
        toggleEditOverlay(true); 
      });
  });
});

// =============== EDIT USER SUBMIT ===============
document.getElementById('editUserForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('action', 'edit');
  fetch(processURL, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(d => handleResponse(d, 'edit'))
    .catch(err => showError(err));
});

// =============== DELETE USER ===============
document.querySelectorAll('.delete-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    if (!confirm('Are you sure you want to delete this user?')) return;
    fetch(processURL, {
      method: 'POST',
      body: new URLSearchParams({ action: 'delete', id })
    })
      .then(r => r.json())
      .then(d => handleResponse(d))
      .catch(err => showError(err));
  });
});

// =============== RESPONSE HANDLER (Uses Toast and custom overlay functions) ===============
function handleResponse(data, actionIdentifier = null) {
  if (actionIdentifier === 'add') toggleAddOverlay(false);
  if (actionIdentifier === 'edit') toggleEditOverlay(false);

  showOverlayToast(data.message, data.status === 'success' ? 'success' : 'danger');

  if (data.status === 'success') setTimeout(() => location.reload(), 1500);
}

// =============== ERROR HANDLER ===============
function showError(err) {
  const alertHTML = `<div class="alert alert-danger" role="alert">Error: ${err.message}</div>`;
  document.getElementById('alert-container').innerHTML = alertHTML;
  console.error("❌", err);
}

// Helper function for Toast
function showOverlayToast(message, type = 'success') {
  const toast = document.getElementById('overlay-toast');
  toast.textContent = message;

  toast.className = `overlay-toast show ${type}`;
  setTimeout(() => toast.classList.remove('show'), 2500);
}
</script>

<style>
/* === VARIABLES === */
:root {
    --brand-blue: #2563eb;
    --brand-blue-dark: #1d4ed8;
    --color-ok: #22c55e;
    --color-warn: #f59e0b;
    --color-danger: #e74c3c;
    --color-ink: #111827;
    --color-gray: #6b7280;
    --color-light: #f8f9fc;
    --color-white: #ffffff;
    --border-color: #d1d5db;
}

/* === CUSTOM OVERLAY STYLES (GUARANTEED OVERLAY) === */
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
  padding: 16px;
  overflow-y: auto;
}

.overlay-card {
  background: var(--color-white);
  border-radius: 12px;
  width: 100%;
  max-width: 550px; 
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  position: relative;
  animation: zoomIn 0.25s ease both;
  min-height: 250px;
}

/* New custom header structure */
.overlay-header {
    padding: 1.5rem 2rem;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    border-bottom: none;
    margin-bottom: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.overlay-title {
    color: var(--color-white);
    font-weight: 700;
    font-size: 1.5rem;
    margin: 0;
    display: flex;
    align-items: center;
}
.overlay-title i {
    margin-right: 10px;
}
.overlay-body {
    padding: 25px; /* Added general padding for the form content */
}

.close-btn {
  background: none;
  border: none;
  font-size: 20px;
  color: var(--color-white) !important;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

@keyframes zoomIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

/* Header Colors - Specific to the image */
.overlay-header.bg-primary {
    background-color: #3366ff !important;
}
.overlay-header.bg-warning {
    background-color: #ff9933 !important;
}


/* === GENERAL LAYOUT === */
.content-wrapper {
  padding: 10px;
  background: var(--color-light);
}
.content-container {
    padding: 25px 40px; 
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.page-header h2 {
    color: var(--color-ink);
    font-size: 22px;
    font-weight: 700;
    margin: 0;
}

/* === CARD & TABLE STYLES === */
.card {
    background-color: var(--color-white);
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    padding: 20px;
    border: 1px solid var(--border-color);
}

/* ... (Table styles) ... */

/* === FORM LAYOUT & PADDING FIXES === */
/* Adjust form group margin for more space */
.form-group {
    margin-bottom: 1.5rem !important; /* Increased vertical space between fields */
}

.form-group label {
    font-weight: 600;
    color: #374151;
    font-size: 14px;
    margin-bottom: 8px; /* Increased space between label and input */
    display: block; /* Ensure label takes full width */
}

.form-control, .form-select {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 15px;
    background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
}

/* New style for small helper text */
small.form-text-helper {
    display: block; /* Force to next line */
    margin-top: 4px;
    font-size: 13px; /* Smaller font */
    color: #6b7280;
    line-height: 1.3;
}


/* Modal Action Buttons (Custom) */
.form-actions-modal {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 25px; /* Increased top margin */
}

.btn-primary-modal, .btn-cancel-modal {
    padding: 10px 18px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    border: none;
    text-decoration: none;
}
.btn-primary-modal {
    background: var(--brand-blue);
    color: var(--color-white);
}
.btn-cancel-modal {
    background: #e5e7eb;
    color: #4b5563;
}
.btn-warning-submit {
    background: var(--color-warn);
    color: var(--color-white);
}

/* ... (Other CSS) ... */

/* === Overlay Toast Notification (Unchanged) === */
.overlay-toast {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) scale(0.9);
  background-color: #ffffff;
  color: #111827;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
  padding: 18px 28px;
  font-size: 16px;
  font-weight: 600;
  text-align: center;
  opacity: 0;
  z-index: 9999;
  pointer-events: none;
  transition: all 0.3s ease;
  min-width: 260px;
}
.overlay-toast.show {
  opacity: 1;
  transform: translate(-50%, -50%) scale(1);
}
.overlay-toast.success {
  border-left: 8px solid #22c55e;
}
.overlay-toast.danger {
  border-left: 8px solid #e74c3c;
}
</style>

<?php include '../includes/footer.php'; ?>