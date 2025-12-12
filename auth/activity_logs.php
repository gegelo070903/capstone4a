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
?>

<div class="content-wrapper">
  <div class="content-container">
    <div class="page-header">
      <h2><i class="fa-solid fa-clock-rotate-left"></i> Activity Logs</h2>
      <span class="badge-info">Read-Only Audit Trail</span>
    </div>

    <!-- Filter Section -->
    <div class="card filter-card">
      <form method="GET" class="filter-form" id="filterForm">
        <div class="filter-group">
          <label for="action">Action Type</label>
          <select name="action" id="action" onchange="document.getElementById('filterForm').submit()">
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
          <input type="date" name="date" id="date" value="<?= htmlspecialchars($date_filter) ?>" onchange="document.getElementById('filterForm').submit()">
        </div>
        <div class="filter-buttons">
          <a href="activity_logs.php" class="btn-clear"><i class="fa-solid fa-times"></i> Clear</a>
        </div>
      </form>
    </div>

    <!-- Logs Table -->
    <div class="card">
      <div class="table-info">
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
          <tbody>
            <?php if ($logs && $logs->num_rows > 0): ?>
              <?php while ($log = $logs->fetch_assoc()): ?>
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
      <?php if ($total_pages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?page=1<?= $action_filter ? '&action=' . urlencode($action_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" class="page-link">&laquo; First</a>
            <a href="?page=<?= $page - 1 ?><?= $action_filter ? '&action=' . urlencode($action_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" class="page-link">&lsaquo; Prev</a>
          <?php endif; ?>
          
          <?php
          $start = max(1, $page - 2);
          $end = min($total_pages, $page + 2);
          for ($i = $start; $i <= $end; $i++):
          ?>
            <a href="?page=<?= $i ?><?= $action_filter ? '&action=' . urlencode($action_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" 
               class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
          
          <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $action_filter ? '&action=' . urlencode($action_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" class="page-link">Next &rsaquo;</a>
            <a href="?page=<?= $total_pages ?><?= $action_filter ? '&action=' . urlencode($action_filter) : '' ?><?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" class="page-link">Last &raquo;</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
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
</style>

<?php include '../includes/footer.php'; ?>
