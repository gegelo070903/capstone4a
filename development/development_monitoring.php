<?php
// ===============================================================
// development_monitoring.php
// Displays all construction development reports with filters,
// sorting, and role-based visibility.
// ===============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../auth/functions.php';

date_default_timezone_set('Asia/Manila');

// ---------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------
require_login();
require_csrf();

// ---------------------------------------------------------------
// Search, Filter, and Sort Logic
// ---------------------------------------------------------------
$search_query = "";
$filter_project_id = "";
$filter_status = "";
$filter_constructor_id = "";
$sort_column = "cr.report_date, cr.start_time";
$sort_order = "DESC";

$where_clauses = [];
$param_types = "";
$param_values = [];

// Search Query
if (!empty($_GET['query'])) {
    $search_query = trim($_GET['query']);
    $where_clauses[] = "(cr.description LIKE ? OR p.name LIKE ? OR u.username LIKE ?)";
    $param_types .= "sss";
    $param_values[] = "%$search_query%";
    $param_values[] = "%$search_query%";
    $param_values[] = "%$search_query%";
}

// Project Filter
if (!empty($_GET['project_id'])) {
    $filter_project_id = intval($_GET['project_id']);
    $where_clauses[] = "cr.project_id = ?";
    $param_types .= "i";
    $param_values[] = $filter_project_id;
}

// Status Filter
if (!empty($_GET['status'])) {
    $filter_status = $_GET['status'];
    $where_clauses[] = "cr.status = ?";
    $param_types .= "s";
    $param_values[] = $filter_status;
}

// Constructor Filter
if (!empty($_GET['constructor_id'])) {
    $filter_constructor_id = intval($_GET['constructor_id']);
    $where_clauses[] = "cr.constructor_id = ?";
    $param_types .= "i";
    $param_values[] = $filter_constructor_id;
}

// Sorting
if (!empty($_GET['sort'])) {
    $sort_map = [
        'date_asc' => ["cr.report_date, cr.start_time", "ASC"],
        'date_desc' => ["cr.report_date, cr.start_time", "DESC"],
        'project_asc' => ["p.name", "ASC"],
        'project_desc' => ["p.name", "DESC"],
        'status_asc' => ["cr.status", "ASC"],
        'status_desc' => ["cr.status", "DESC"],
        'reporter_asc' => ["u.username", "ASC"],
        'reporter_desc' => ["u.username", "DESC"]
    ];
    if (isset($sort_map[$_GET['sort']])) {
        [$sort_column, $sort_order] = $sort_map[$_GET['sort']];
    }
}

// ---------------------------------------------------------------
// Main Query
// ---------------------------------------------------------------
$sql = "
    SELECT cr.*, p.name AS project_name, u.username AS reporter_name
    FROM construction_reports cr
    JOIN projects p ON cr.project_id = p.id
    JOIN users u ON cr.constructor_id = u.id
";

if ($where_clauses) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY {$sort_column} {$sort_order}";

$stmt_reports = $conn->prepare($sql);
if (!empty($param_types)) {
    $stmt_reports->bind_param($param_types, ...$param_values);
}
$stmt_reports->execute();
$reports_result = $stmt_reports->get_result();
$reports = $reports_result->fetch_all(MYSQLI_ASSOC);
$stmt_reports->close();

// ---------------------------------------------------------------
// Filter Dropdown Data
// ---------------------------------------------------------------
$projects_for_filter = $conn->query("SELECT id, name FROM projects ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$constructors_for_filter = $conn->query("SELECT id, username FROM users WHERE role = 'constructor' ORDER BY username ASC")->fetch_all(MYSQLI_ASSOC);

// ---------------------------------------------------------------
// Flash or Status Messages
// ---------------------------------------------------------------
ob_start();
flash_message();
$status_message = ob_get_clean();

// ---------------------------------------------------------------
// Include Header Layout
// ---------------------------------------------------------------
include '../includes/header.php';
?>

<!-- ===================== STYLES ===================== -->
<style>
/* FULL STYLES COPIED FROM YOUR VERSION */

.main-content-wrapper {
    padding: 30px;
    max-width: 1200px;
    margin: 0 auto;
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
}
.alert {
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-weight: bold;
    text-align: center;
    font-size: 0.9em;
    opacity: 1;
    transition: opacity 0.5s ease-out;
}
.alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6fb; }
.alert.fade-out { opacity: 0; }

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}
.section-header h3 {
    font-size: 2em;
    color: #333;
    margin: 0;
}

.reports-control-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 25px;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
}

.filter-group, .sort-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-group label, .sort-group label {
    font-weight: 500;
    color: #555;
    font-size: 0.9em;
    white-space: nowrap;
}

.filter-group select, .sort-group select {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 0.9em;
    cursor: pointer;
    background-color: #fff;
    min-width: 150px;
    flex-grow: 1;
}
.filter-group button {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.9em;
    transition: background-color 0.2s ease;
    white-space: nowrap;
}
.filter-group button:hover { background-color: #0056b3; }

.search-bar-reports {
    display: flex;
    align-items: center;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 5px 10px;
    width: 300px;
    max-width: 100%;
    background-color: #fff;
    box-sizing: border-box;
}
.search-bar-reports input {
    border: none;
    outline: none;
    width: 100%;
    padding: 5px;
    font-size: 0.9em;
    background-color: transparent;
}
.search-bar-reports button {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8em;
    background-color: #007bff;
    color: white;
    margin-left: 5px;
}
.search-bar-reports button:hover { background-color: #0056b3; }

.reports-table-container {
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    max-height: calc(100vh - 300px);
    overflow-y: auto;
    overflow-x: hidden;
    padding: 20px;
}
.sticky-header-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
.sticky-header-table thead {
    position: sticky;
    top: 0;
    z-index: 5;
    background-color: #e9edf2;
}
.sticky-header-table th, .sticky-header-table td {
    padding: 10px 8px;
    vertical-align: top;
    white-space: normal;
    word-wrap: break-word;
    font-size: 0.85em;
    border-bottom: 1px solid #dee2e6;
    line-height: 1.3;
}
.sticky-header-table th {
    font-weight: 600;
    color: #495057;
    text-align: left;
    padding-top: 12px;
    padding-bottom: 12px;
}
.text-center { text-align: center; }
.text-right { text-align: right; }

.report-status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: bold;
    color: white;
    text-transform: uppercase;
    display: inline-block;
}
.report-status-badge.pending { background-color: #ffc107; color: #333; }
.report-status-badge.ongoing { background-color: #007bff; }
.report-status-badge.complete { background-color: #28a745; }

.material-usage-list {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 0.8em;
    color: #666;
}
.material-usage-list li {
    margin-bottom: 2px;
    padding: 1px 0;
    border-bottom: 1px dotted #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}
.material-usage-list li:last-child { border-bottom: none; }
.material-usage-list .quantity-display {
    white-space: nowrap;
    font-weight: 500;
    color: #333;
}
.material-usage-list .material-name {
    flex-grow: 1;
    margin-left: 5px;
}

.deduct-btn {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 3px 6px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.7em;
}
.deduct-btn:hover { background-color: #0056b3; }
.deducted-status {
    color: #28a745;
    font-weight: bold;
    font-size: 0.7em;
    margin-left: 5px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.deducted-info {
    font-size: 0.65em;
    color: #888;
    display: block;
    margin-top: 1px;
    line-height: 1.1;
}

.action-buttons-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: center;
}
.actions-cell a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-weight: bold;
    padding: 5px 8px;
    font-size: 0.8em;
    border-radius: 5px;
    min-width: 55px;
}
.actions-cell .btn-edit { background-color: #ffc107; color: #333; }
.actions-cell .btn-edit:hover { background-color: #e0a800; }
.actions-cell .btn-danger { background-color: #dc3545; color: white; }
.actions-cell .btn-danger:hover { background-color: #c82333; }

.no-data-message {
    text-align: center;
    padding: 30px;
    font-size: 1.1em;
    color: #777;
    font-style: italic;
    background-color: #f0f2f5;
    border-radius: 8px;
    margin-top: 20px;
}
</style>

<!-- ===================== JS ===================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade-out');
            alert.addEventListener('transitionend', () => alert.remove());
        }, 5000);
    });

    window.deductMaterial = function(rmuId, projectId) {
        if (!confirm('Are you sure you want to deduct this material quantity from inventory?')) return;
        const fd = new FormData();
        fd.append('rmu_id', rmuId);
        fd.append('project_id', projectId);
        fetch('../development/process_deduct_material_usage.php', {
            method: 'POST', body: fd
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.href = `development_monitoring.php?status=material_deducted_success&project_id=${projectId}`;
            else alert('Error deducting material: ' + d.message);
        })
        .catch(e => alert('An error occurred while deducting material.'));
    };
});
</script>

<?php include '../includes/footer.php'; ?>