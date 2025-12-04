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
              <th style="width: 5%">#</th>
              <th style="width: 40%">Username</th>
              <th style="width: 25%">Role</th>
              <th style="width: 20%; text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if ($users && $users->num_rows > 0):
              while ($row = $users->fetch_assoc()):
            ?>
              <tr data-id="<?= $row['id']; ?>">
                <td><?= htmlspecialchars($row['id']); ?></td>
                <td><?= htmlspecialchars($row['username']); ?></td>
                <td>
                  <span class="role-badge <?= get_role_class($row['role']); ?>">
                    <?= htmlspecialchars(ucfirst($row['role'])); ?>
                  </span>
                </td>
                <td class="actions">
                  <button class="btn-icon btn-edit edit-btn" data-id="<?= $row['id']; ?>" title="Edit">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button class="btn-icon btn-delete delete-btn" data-id="<?= $row['id']; ?>" title="Delete">
                    <i class="fas fa-trash-alt"></i>
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
<!-- REMOVED Bootstrap modal classes -->
<div class="overlay" id="addUserModal" style="display: none;">
  <div class="overlay-card">
    
    <div class="overlay-header bg-primary">
      <h5 class="overlay-title"><i class="fa-solid fa-user-plus"></i> Add User</h5>
      <!-- Changed to custom close button for custom overlay -->
      <button type="button" class="close-btn" onclick="toggleAddOverlay(false)">✕</button>
    </div>
    
    <div class="overlay-body">
      <form id="addUserForm">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input type="text" class="form-control" name="username" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" class="form-control" name="password" required>
        </div>
        <div class="form-group">
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
<!-- REMOVED Bootstrap modal classes -->
<div class="overlay" id="editUserModal" style="display: none;">
  <div class="overlay-card">
    
    <div class="overlay-header bg-warning">
      <h5 class="overlay-title"><i class="fa-solid fa-pencil-square"></i> Edit User</h5>
      <!-- Changed to custom close button for custom overlay -->
      <button type="button" class="close-btn" onclick="toggleEditOverlay(false)">✕</button>
    </div>
    
    <div class="overlay-body">
      <form id="editUserForm">
        <input type="hidden" name="id" id="editUserId">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input type="text" class="form-control" name="username" id="editUsername" required>
        </div>
        <div class="form-group">
          <label class="form-label">New Password</label>
          <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current">
          <small class="form-text-helper">Leave blank to keep current password.</small>
        </div>
        <div class="form-group">
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

<!-- CRITICAL FIX: Bootstrap JS Bundle (must load before custom script) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// =============== CUSTOM OVERLAY FUNCTIONS (Final Custom Control) ===============
// Using style.display = flex/none for reliable overlay management
function toggleAddOverlay(show) {
    const modalElement = document.getElementById('addUserModal');
    modalElement.style.display = show ? 'flex' : 'none'; 
    if (show) document.getElementById('addUserForm').reset();
}

function toggleEditOverlay(show) {
    const modalElement = document.getElementById('editUserModal');
    modalElement.style.display = show ? 'flex' : 'none';
    if (show) document.getElementById('editUserForm').querySelector('input[name="password"]').value = '';
    if (!show) document.getElementById('editUserForm').reset();
}
// =============== END CUSTOM OVERLAY FUNCTIONS ===============


const processURL = "process_user.php";

// Helper function to process the raw fetch response
function processRawResponse(response) {
    return response.text().then(text => {
        if (!text) {
            throw new Error('Empty response received from server.');
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Invalid JSON response: ' + text.substring(0, 50) + '...');
        }
    });
}


// =============== ADD USER ===============
document.getElementById('addUserForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('action', 'add');

  fetch(processURL, { method: 'POST', body: formData })
    .then(processRawResponse) 
    .then(d => handleResponse(d, 'add'))
    .catch(err => showError(err));
});

// =============== EDIT USER POPUP ===============
document.querySelectorAll('.edit-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    fetch(`${processURL}?action=get&id=${id}`)
      .then(processRawResponse) 
      .then(user => {
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUsername').value = user.username;
        document.getElementById('editRole').value = user.role;
        toggleEditOverlay(true); 
      })
      .catch(err => showError(err));
  });
});

// =============== EDIT USER SUBMIT ===============
document.getElementById('editUserForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('action', 'edit');
  fetch(processURL, { method: 'POST', body: formData })
    .then(processRawResponse) 
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
      .then(processRawResponse) 
      .then(d => handleResponse(d, 'delete'))
      .catch(err => showError(err));
  });
});


// =============== DYNAMIC DOM UPDATE HELPERS (FINAL) ===============

function attachRowEvents(row) {
  const editBtn = row.querySelector('.btn-edit');
  const deleteBtn = row.querySelector('.btn-delete');
  
  if (editBtn) {
    editBtn.addEventListener('click', () => {
      const id = row.dataset.id;
      fetch(`${processURL}?action=get&id=${id}`)
        .then(processRawResponse) 
        .then(user => {
          document.getElementById('editUserId').value = user.id;
          document.getElementById('editUsername').value = user.username;
          document.getElementById('editRole').value = user.role;
          toggleEditOverlay(true);
        });
    });
  }

  if (deleteBtn) {
    deleteBtn.addEventListener('click', () => {
      const id = row.dataset.id;
      if (!confirm('Are you sure you want to delete this user?')) return;
      fetch(processURL, {
        method: 'POST',
        body: new URLSearchParams({ action: 'delete', id })
      })
        .then(processRawResponse) 
        .then(d => handleResponse(d, 'delete'))
        .catch(err => showError(err));
    });
  }
}

function addUserRow(user) {
  const tbody = document.querySelector('#usersTable tbody');
  const newRow = document.createElement('tr');
  const roleDisplay = user.role.charAt(0).toUpperCase() + user.role.slice(1);

  newRow.setAttribute('data-id', user.id);
  newRow.innerHTML = `
    <td>${user.id}</td>
    <td>${user.username}</td>
    <td>
      <span class="role-badge ${user.role === 'admin' ? 'role-admin' : 'role-constructor'}">
        ${roleDisplay}
      </span>
    </td>
    <td class="actions">
      <button class="btn-icon btn-edit edit-btn" data-id="${user.id}" title="Edit">
        <i class="fas fa-edit"></i>
      </button>
      <button class="btn-icon btn-delete delete-btn" data-id="${user.id}" title="Delete">
        <i class="fas fa-trash-alt"></i>
      </button>
    </td>
  `;
  
  // Find and remove "No users found" row if it exists
  const emptyRow = tbody.querySelector('td.empty');
  if (emptyRow) emptyRow.closest('tr').remove();

  tbody.appendChild(newRow);
  attachRowEvents(newRow); // CRITICAL: Attach events to the new buttons
}

function updateUserRow(user) {
  const row = document.querySelector(`#usersTable tr[data-id="${user.id}"]`);
  if (!row) return;
  const roleDisplay = user.role.charAt(0).toUpperCase() + user.role.slice(1);
  
  // Update username cell
  row.querySelector('td:nth-child(2)').textContent = user.username;
  
  // Update role badge
  const badge = row.querySelector('.role-badge');
  badge.textContent = roleDisplay;
  badge.className = `role-badge ${user.role === 'admin' ? 'role-admin' : 'role-constructor'}`;
}

function removeUserRow(id) {
  const row = document.querySelector(`#usersTable tr[data-id="${id}"]`);
  if (row) row.remove();
  
  // Optional: Check if table is now empty and add 'No users found' row
  const tbody = document.querySelector('#usersTable tbody');
  if (tbody.children.length === 0) {
      const emptyRow = document.createElement('tr');
      emptyRow.innerHTML = `<td colspan="4" class="empty">No users found.</td>`;
      tbody.appendChild(emptyRow);
  }
}

// =============== RESPONSE HANDLER (Updated for dynamic table update) ===============
function handleResponse(data, actionIdentifier = null) {
  // Hide overlay using the custom function
  if (actionIdentifier === 'add') toggleAddOverlay(false);
  if (actionIdentifier === 'edit') toggleEditOverlay(false);

  // Show overlay message
  showOverlayToast(data.message, data.status === 'success' ? 'success' : 'danger');

  // DYNAMIC TABLE UPDATE LOGIC
  if (data.status === 'success') {
    if (data.action === 'add') addUserRow(data.user);
    else if (data.action === 'edit') updateUserRow(data.user);
    else if (data.action === 'delete') removeUserRow(data.user_id);
    
    // NOTE: location.reload() is removed
  }
}

function showOverlayToast(message, type = 'success') {
  const toast = document.getElementById('overlay-toast');
  toast.textContent = message;

  toast.className = `overlay-toast show ${type}`;
  setTimeout(() => toast.classList.remove('show'), 2500);
}

// Initial event attachment for existing rows (on DOM load)
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#usersTable tbody tr').forEach(row => {
        attachRowEvents(row);
    });
});
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

/* === CUSTOM OVERLAY STYLES (Final Custom Control) === */
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
    /* FIX: Set different vertical and horizontal padding: 
       30px for top/bottom, 
       40px for right, 
       20px for left. */
    padding: 30px 40px 30px 20px; 
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

/* FIX: Table Spacing and Padding (Crowdedness Fix) */
.data-table th, .data-table td {
    padding: 15px 12px; /* Increased vertical padding for space */
    border-bottom: 1px solid #ddd;
    font-size: 14px;
    text-align: left; /* Aligned left for content flow */
}
.data-table th {
    font-weight: 700;
    text-transform: uppercase;
    color: #4b5563;
    text-align: left; /* Aligned left for header flow */
}

/* FIX: Column Alignment Overrides for centered data */
.data-table th:nth-child(1), .data-table td:nth-child(1) { width: 5%; text-align: center; } /* # */
.data-table th:nth-child(2), .data-table td:nth-child(2) { width: 40%; text-align: left; } /* Username (Left-aligned) */
.data-table th:nth-child(3), .data-table td:nth-child(3) { width: 25%; text-align: center; } /* Role */
.data-table th:nth-child(4) { 
    width: 20%; 
    /* The new block below overrides the action column styles */
}


/* === FIX: Perfect alignment for ACTIONS column === */
.data-table th:last-child {
  width: 140px;                 /* fixed column width */
  text-align: center !important;
  vertical-align: middle !important;
  /* Reintroduce padding for border continuity, maintaining original 15px top/bottom */
  padding: 15px 12px !important; 
}

.data-table td.actions {
  width: 140px;                 /* fixed column width */
  vertical-align: middle !important;
  
  /* Reintroduce padding for border continuity, maintaining original 15px top/bottom */
  padding: 15px 12px !important;
  
  /* Now use flex to center the content inside the padded area */
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10px;                    /* consistent spacing between buttons */
}

/* Uniform icon buttons */
.btn-icon {
  width: 32px;
  height: 32px;
  display: inline-flex;
  justify-content: center;
  align-items: center;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 15px;
  transition: all 0.2s ease;
}

/* Edit button styling */
.btn-edit {
  background-color: #f59e0b;
  color: #fff;
}
.btn-edit:hover {
  background-color: #d97706;
}

/* Delete button styling */
.btn-delete {
  background-color: #e74c3c;
  color: #fff;
}
.btn-delete:hover {
  background-color: #c0392b;
}

/* Optional: subtle lift on hover */
.btn-icon:hover {
  transform: scale(1.1);
}


/* FIX: Add New User Button Style */
.btn-add {
    background-color: var(--brand-blue);
    color: var(--color-white);
    padding: 10px 18px; /* Wider padding */
    border-radius: 8px; /* Slightly more rounded */
    font-weight: 600;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
    transition: background-color 0.2s;
}
.btn-add:hover {
    background-color: var(--brand-blue-dark);
}

/* FIX: Role Badge */
.role-badge {
    background-color: #e5e7eb; /* Light Gray background for constructor/default */
    color: #374151; /* Dark text for light background */
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap; /* Prevent badge text from wrapping */
}
.role-admin { background-color: var(--brand-blue); color: var(--color-white); } /* Blue background for Admin */


/* === FORM LAYOUT & PADDING FIXES === */
/* Adjust form group margin for more space */
.form-group {
    margin-bottom: 1.5rem !important; /* Increased vertical space between fields */
}

/* FIX: Added display: block to label to force input onto new line */
.form-group label {
    font-weight: 600;
    color: #374151;
    font-size: 14px;
    margin-bottom: 8px; /* Increased space between label and input */
    display: block; /* IMPORTANT FIX: Forces input to new line */
}

.form-control, .form-select {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 15px;
    background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
    width: 100%; /* IMPORTANT: Ensure input takes full width */
}

/* FIX 2: Limit the width of the role select dropdown */
.form-group .form-select {
    width: auto; /* Allow it to shrink based on content/max-width */
    max-width: 250px; /* Set a max width */
    display: block;
}

/* New style for small helper text */
small.form-text-helper {
    display: block; 
    margin-top: 4px;
    font-size: 13px; 
    color: #6b7280;
    line-height: 1.3;
}


/* Modal Action Buttons (Custom) */
.form-actions-modal {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 25px; 
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