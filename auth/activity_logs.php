<?php
// auth/activity_logs.php - Read-only Activity Logs (Super Admin Only)

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

// Only super admin can access this page
if (!is_super_admin()) {
    header("Location: ../dashboard.php?error=unauthorized");
    exit;
}

// Check if AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Pagination
$per_page = 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Filter by action type
$action_filter = isset($_GET['action']) ? trim($_GET['action']) : '';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($action_filter)) {
    $where_conditions[] = "action = ?";
    $params[] = $action_filter;
    $types .= 's';
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(created_at) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM activity_logs $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $per_page);

// Get logs
$logs_sql = "SELECT * FROM activity_logs $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$logs_stmt = $conn->prepare($logs_sql);
if (!empty($params)) {
    $log_params = $params;
    $log_params[] = $per_page;
    $log_params[] = $offset;
    $log_types = $types . 'ii';
    $logs_stmt->bind_param($log_types, ...$log_params);
} else {
    $logs_stmt->bind_param('ii', $per_page, $offset);
}
$logs_stmt->execute();
$logs_result = $logs_stmt->get_result();
$logs_stmt->close();

// If AJAX, return JSON
if ($isAjax) {
    $logs_data = [];
    while ($log = $logs_result->fetch_assoc()) {
        $logs_data[] = $log;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'logs' => $logs_data,
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'per_page' => $per_page
    ]);
    exit;
}

// Get distinct actions for filter dropdown
$actions_result = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC");
$action_types = [];
while ($row = $actions_result->fetch_assoc()) {
    $action_types[] = $row['action'];
}

include '../includes/header.php';

// Pagination
$per_page = 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Filter by action type
$action_filter = isset($_GET['action']) ? trim($_GET['action']) : '';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($action_filter)) {
    $where_conditions[] = "action = ?";
    $params[] = $action_filter;
    $types .= 's';
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(created_at) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM activity_logs $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $per_page);

// Get logs
$logs_sql = "SELECT * FROM activity_logs $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$logs_stmt = $conn->prepare($logs_sql);
if (!empty($params)) {
    $params[] = $per_page;
    $params[] = $offset;
    $types .= 'ii';
    $logs_stmt->bind_param($types, ...$params);
} else {
    $logs_stmt->bind_param('ii', $per_page, $offset);
}
$logs_stmt->execute();
$logs = $logs_stmt->get_result();
$logs_stmt->close();

// Get distinct actions for filter dropdown
$actions_result = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC");
$action_types = [];
while ($row = $actions_result->fetch_assoc()) {
    $action_types[] = $row['action'];
}

// Reset logs result for initial render
$logs_result->data_seek(0);
?>

<div class="content-wrapper">
  <div class="content-container">
    <div class="page-header">
      <h2><i class="fa-solid fa-clock-rotate-left"></i> Activity Logs</h2>
      <span class="badge-info">Read-Only Audit Trail</span>
      <button type="button" class="btn-refresh" onclick="refreshLogs()" title="Refresh Logs">
        <i class="fa-solid fa-sync-alt" id="refreshIcon"></i> Refresh
      </button>
    </div>

    <!-- Filter Section -->
    <div class="card filter-card">
      <form method="GET" class="filter-form" id="filterForm" onsubmit="return filterLogs(event)">
        <div class="filter-group">
          <label for="action">Action Type</label>
          <select name="action" id="action" onchange="filterLogs()">
            <option value="">All Actions</option>
            <?php foreach ($action_types as $type): ?>
              <option value="<?= htmlspecialchars($type) ?>" <?= $action_filter === $type ? 'selected' : '' ?>>
                <?= htmlspecialchars($type) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label for="date">Date</label>
          <input type="date" name="date" id="date" value="<?= htmlspecialchars($date_filter) ?>" onchange="filterLogs()">
        </div>
        <div class="filter-buttons">
          <button type="button" class="btn-clear" onclick="clearFilters()"><i class="fa-solid fa-times"></i> Clear</button>
        </div>
      </form>
    </div>

    <!-- Logs Table -->
    <div class="card">
      <div class="table-info" id="tableInfo">
        <p>Showing <?= number_format($total_records) ?> total records (Page <?= $page ?> of <?= max(1, $total_pages) ?>)</p>
      </div>
      
      <div class="table-responsive">
        <table class="data-table" id="logsTable">
          <thead>
            <tr>
              <th style="width: 5%">#</th>
              <th style="width: 12%">Date/Time</th>
              <th style="width: 12%">User</th>
              <th style="width: 12%">Action</th>
              <th style="width: 44%">Details</th>
              <th style="width: 15%">IP Address</th>
            </tr>
          </thead>
          <tbody id="logsTableBody">
            <?php if ($logs_result && $logs_result->num_rows > 0): ?>
              <?php while ($log = $logs_result->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($log['id']) ?></td>
                  <td><?= date('M d, Y h:i A', strtotime($log['created_at'])) ?></td>
                  <td><?= htmlspecialchars($log['username']) ?></td>
                  <td>
                    <span class="action-badge action-<?= strtolower(str_replace('_', '-', $log['action'])) ?>">
                      <?= htmlspecialchars($log['action']) ?>
                    </span>
                  </td>
                  <td class="details-cell"><?= htmlspecialchars($log['details']) ?></td>
                  <td><?= htmlspecialchars($log['ip_address']) ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" class="empty">No activity logs found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination" id="paginationContainer">
      <?php if ($total_pages > 1): ?>
          <?php if ($page > 1): ?>
            <a href="javascript:void(0)" onclick="goToPage(1)" class="page-link">&laquo; First</a>
            <a href="javascript:void(0)" onclick="goToPage(<?= $page - 1 ?>)" class="page-link">&lsaquo; Prev</a>
          <?php endif; ?>
          
          <?php
          $start = max(1, $page - 2);
          $end = min($total_pages, $page + 2);
          for ($i = $start; $i <= $end; $i++):
          ?>
            <a href="javascript:void(0)" onclick="goToPage(<?= $i ?>)" 
               class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
          
          <?php if ($page < $total_pages): ?>
            <a href="javascript:void(0)" onclick="goToPage(<?= $page + 1 ?>)" class="page-link">Next &rsaquo;</a>
            <a href="javascript:void(0)" onclick="goToPage(<?= $total_pages ?>)" class="page-link">Last &raquo;</a>
          <?php endif; ?>
      <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<style>
.content-wrapper {
  padding: 20px;
}

.content-container {
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  color: #1f2937;
}

.badge-info {
  background: #dbeafe;
  color: #1e40af;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.card {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  margin-bottom: 20px;
}

.filter-card {
  padding: 20px 24px;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  position: sticky;
  top: 0;
  z-index: 100;
}

.filter-form {
  display: flex;
  align-items: flex-end;
  gap: 20px;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.filter-group label {
  font-weight: 600;
  color: #374151;
  font-size: 13px;
}

.filter-group select,
.filter-group input[type="date"] {
  padding: 10px 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  min-width: 180px;
  background: #fff;
}

.filter-group select:focus,
.filter-group input[type="date"]:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.filter-buttons {
  display: flex;
  gap: 10px;
  align-items: center;
}

.btn-filter {
  background: #2563eb;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background 0.2s;
}

.btn-filter:hover {
  background: #1d4ed8;
}

.btn-clear {
  background: #f3f4f6;
  color: #374151;
  padding: 10px 20px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  font-size: 14px;
  border: 1px solid #d1d5db;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.2s;
}

.btn-clear:hover {
  background: #e5e7eb;
  color: #1f2937;
}

.table-info {
  margin-bottom: 15px;
  color: #6b7280;
  font-size: 14px;
}

.table-responsive {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
}

.data-table th {
  background: #f9fafb;
  font-weight: 600;
  color: #374151;
}

.data-table tr:hover {
  background: #f9fafb;
}

.details-cell {
  max-width: 400px;
  word-wrap: break-word;
  font-size: 13px;
  color: #4b5563;
}

.action-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  white-space: nowrap;
}

/* Action type colors */
.action-login { background: #d1fae5; color: #065f46; }
.action-logout { background: #fee2e2; color: #991b1b; }
.action-add-project { background: #dbeafe; color: #1e40af; }
.action-edit-project { background: #e0f2fe; color: #0369a1; }
.action-delete-project, .action-archive-project { background: #fef3c7; color: #92400e; }
.action-add-material { background: #fef3c7; color: #92400e; }
.action-edit-material { background: #fef9c3; color: #a16207; }
.action-delete-material { background: #fee2e2; color: #991b1b; }
.action-add-checklist { background: #e0e7ff; color: #3730a3; }
.action-complete-checklist { background: #d1fae5; color: #065f46; }
.action-delete-checklist { background: #fee2e2; color: #991b1b; }
.action-add-report { background: #fce7f3; color: #9d174d; }
.action-edit-report { background: #fbcfe8; color: #be185d; }
.action-delete-report { background: #fee2e2; color: #991b1b; }
.action-add-account { background: #cffafe; color: #0e7490; }
.action-edit-account { background: #a5f3fc; color: #0891b2; }
.action-delete-account { background: #fee2e2; color: #991b1b; }
.action-generate-pdf { background: #f3e8ff; color: #6b21a8; }
.action-upload-image { background: #fef3c7; color: #b45309; }
.action-delete-image { background: #fee2e2; color: #991b1b; }
.action-restore-project { background: #d1fae5; color: #065f46; }
.action-confirm-project { background: #d1fae5; color: #065f46; }
.action-cancel-project { background: #fee2e2; color: #991b1b; }

.empty {
  text-align: center;
  color: #9ca3af;
  padding: 40px 20px !important;
}

.pagination {
  display: flex;
  justify-content: center;
  gap: 5px;
  margin-top: 20px;
  flex-wrap: wrap;
}

.page-link {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  text-decoration: none;
  color: #374151;
  font-size: 14px;
}

.page-link:hover {
  background: #f3f4f6;
}

.page-link.active {
  background: #2563eb;
  color: white;
  border-color: #2563eb;
}

.btn-refresh {
  background: #10b981;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-left: auto;
  transition: background 0.2s;
}

.btn-refresh:hover {
  background: #059669;
}

.btn-refresh.loading i {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.loading-row td {
  text-align: center;
  padding: 40px !important;
  color: #6b7280;
}
</style>

<script>
let currentPage = <?= $page ?>;

function formatDate(dateStr) {
    const date = new Date(dateStr);
    const options = { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true };
    return date.toLocaleDateString('en-US', options).replace(',', '');
}

function getActionClass(action) {
    return 'action-' + action.toLowerCase().replace(/_/g, '-');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function fetchLogs(page = 1) {
    const actionFilter = document.getElementById('action').value;
    const dateFilter = document.getElementById('date').value;
    
    const params = new URLSearchParams();
    params.append('page', page);
    if (actionFilter) params.append('action', actionFilter);
    if (dateFilter) params.append('date', dateFilter);
    
    const tbody = document.getElementById('logsTableBody');
    tbody.innerHTML = '<tr class="loading-row"><td colspan="6"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</td></tr>';
    
    fetch('activity_logs.php?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            currentPage = data.current_page;
            renderLogs(data.logs);
            renderPagination(data.current_page, data.total_pages);
            updateTableInfo(data.total_records, data.current_page, data.total_pages);
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="empty">Error loading logs.</td></tr>';
        }
    })
    .catch(err => {
        console.error(err);
        tbody.innerHTML = '<tr><td colspan="6" class="empty">Error loading logs.</td></tr>';
    });
}

function renderLogs(logs) {
    const tbody = document.getElementById('logsTableBody');
    
    if (logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty">No activity logs found.</td></tr>';
        return;
    }
    
    let html = '';
    logs.forEach(log => {
        html += `<tr>
            <td>${escapeHtml(log.id)}</td>
            <td>${formatDate(log.created_at)}</td>
            <td>${escapeHtml(log.username)}</td>
            <td>
                <span class="action-badge ${getActionClass(log.action)}">
                    ${escapeHtml(log.action)}
                </span>
            </td>
            <td class="details-cell">${escapeHtml(log.details)}</td>
            <td>${escapeHtml(log.ip_address)}</td>
        </tr>`;
    });
    
    tbody.innerHTML = html;
}

function renderPagination(currentPage, totalPages) {
    const container = document.getElementById('paginationContainer');
    
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '';
    
    if (currentPage > 1) {
        html += `<a href="javascript:void(0)" onclick="goToPage(1)" class="page-link">&laquo; First</a>`;
        html += `<a href="javascript:void(0)" onclick="goToPage(${currentPage - 1})" class="page-link">&lsaquo; Prev</a>`;
    }
    
    const start = Math.max(1, currentPage - 2);
    const end = Math.min(totalPages, currentPage + 2);
    
    for (let i = start; i <= end; i++) {
        html += `<a href="javascript:void(0)" onclick="goToPage(${i})" class="page-link ${i === currentPage ? 'active' : ''}">${i}</a>`;
    }
    
    if (currentPage < totalPages) {
        html += `<a href="javascript:void(0)" onclick="goToPage(${currentPage + 1})" class="page-link">Next &rsaquo;</a>`;
        html += `<a href="javascript:void(0)" onclick="goToPage(${totalPages})" class="page-link">Last &raquo;</a>`;
    }
    
    container.innerHTML = html;
}

function updateTableInfo(totalRecords, currentPage, totalPages) {
    const info = document.getElementById('tableInfo');
    info.innerHTML = `<p>Showing ${totalRecords.toLocaleString()} total records (Page ${currentPage} of ${Math.max(1, totalPages)})</p>`;
}

function goToPage(page) {
    fetchLogs(page);
}

function filterLogs(event) {
    if (event) event.preventDefault();
    fetchLogs(1);
    return false;
}

function clearFilters() {
    document.getElementById('action').value = '';
    document.getElementById('date').value = '';
    fetchLogs(1);
}

function refreshLogs() {
    const btn = document.querySelector('.btn-refresh');
    btn.classList.add('loading');
    
    fetchLogs(currentPage);
    
    setTimeout(() => {
        btn.classList.remove('loading');
        if (typeof showToast === 'function') {
            showToast('Activity logs refreshed!', 'success');
        }
    }, 500);
}
</script>

<?php include '../includes/footer.php'; ?>
