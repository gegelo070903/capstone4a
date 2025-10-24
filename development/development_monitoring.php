<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// --- PHP Logic for Search/Filter/Sort ---
$search_query = "";
$filter_project_id = "";
$filter_status = "";
$filter_constructor_id = "";
$sort_column = "cr.report_date, cr.start_time"; // Default sort
$sort_order = "DESC";

$where_clauses = [];
$param_types = "";
$param_values = [];

// Handle Search Query
if (isset($_GET['query']) && !empty(trim($_GET['query']))) {
    $search_query = trim($_GET['query']);
    $where_clauses[] = "(cr.description LIKE ? OR p.name LIKE ? OR u.username LIKE ?)";
    $param_types .= "sss";
    $param_values[] = "%$search_query%";
    $param_values[] = "%$search_query%";
    $param_values[] = "%$search_query%";
}

// Handle Project Filter
if (isset($_GET['project_id']) && $_GET['project_id'] !== '') {
    $filter_project_id = intval($_GET['project_id']);
    $where_clauses[] = "cr.project_id = ?";
    $param_types .= "i";
    $param_values[] = $filter_project_id;
}

// Handle Status Filter
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $filter_status = $_GET['status'];
    $where_clauses[] = "cr.status = ?";
    $param_types .= "s";
    $param_values[] = $filter_status;
}

// Handle Constructor Filter
if (isset($_GET['constructor_id']) && $_GET['constructor_id'] !== '') {
    $filter_constructor_id = intval($_GET['constructor_id']);
    $where_clauses[] = "cr.constructor_id = ?";
    $param_types .= "i";
    $param_values[] = $filter_constructor_id;
}

// Handle Sorting
if (isset($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'date_asc':
            $sort_column = "cr.report_date, cr.start_time";
            $sort_order = "ASC";
            break;
        case 'date_desc':
            $sort_column = "cr.report_date, cr.start_time";
            $sort_order = "DESC";
            break;
        case 'project_asc':
            $sort_column = "p.name";
            $sort_order = "ASC";
            break;
        case 'project_desc':
            $sort_column = "p.name";
            $sort_order = "DESC";
            break;
        case 'status_asc':
            $sort_column = "cr.status";
            $sort_order = "ASC";
            break;
        case 'status_desc':
            $sort_column = "cr.status";
            $sort_order = "DESC";
            break;
        case 'reporter_asc':
            $sort_column = "u.username";
            $sort_order = "ASC";
            break;
        case 'reporter_desc':
            $sort_column = "u.username";
            $sort_order = "DESC";
            break;
        default: // Default to newest date if 'sort' is set but invalid
            $sort_column = "cr.report_date, cr.start_time";
            $sort_order = "DESC";
            break;
    }
}


// Base query to fetch all reports
$sql = "SELECT
            cr.*,
            p.name AS project_name,
            u.username AS reporter_name
        FROM construction_reports cr
        JOIN projects p ON cr.project_id = p.id
        JOIN users u ON cr.constructor_id = u.id";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY {$sort_column} {$sort_order}";

$stmt_reports = $conn->prepare($sql);
if (!empty($param_types)) {
    $stmt_reports->bind_param($param_types, ...$param_values);
}
$stmt_reports->execute();
$reports_result = $stmt_reports->get_result();
$reports = [];
while ($report_row = $reports_result->fetch_assoc()) {
    $reports[] = $report_row;
}
$stmt_reports->close();

// Fetch all projects for filter dropdown
$projects_for_filter = [];
$stmt_all_projects = $conn->prepare("SELECT id, name FROM projects ORDER BY name ASC");
$stmt_all_projects->execute();
$all_projects_result = $stmt_all_projects->get_result();
while ($proj = $all_projects_result->fetch_assoc()) {
    $projects_for_filter[] = $proj;
}
$stmt_all_projects->close();

// Fetch all constructors for filter dropdown
$constructors_for_filter = [];
$stmt_all_constructors = $conn->prepare("SELECT id, username FROM users WHERE role = 'constructor' ORDER BY username ASC");
$stmt_all_constructors->execute();
$all_constructors_result = $stmt_all_constructors->get_result();
while ($con = $all_constructors_result->fetch_assoc()) {
    $constructors_for_filter[] = $con;
}
$stmt_all_constructors->close();


// --- Handle Status Messages (similar to other pages) ---
$status_message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'report_added_success') {
        $status_message = '<div class="alert success">Daily report added successfully!</div>';
    } elseif ($_GET['status'] === 'report_added_error') {
        $error_details = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error adding daily report. Please try again.';
        $status_message = '<div class="alert error">' . $error_details . '</div>';
    } elseif ($_GET['status'] === 'report_updated_success') {
        $status_message = '<div class="alert success">Daily report updated successfully!</div>';
    } elseif ($_GET['status'] === 'report_updated_error') {
        $error_details = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error updating daily report. Please try again.';
        $status_message = '<div class="alert error">' . $error_details . '</div>';
    } elseif ($_GET['status'] === 'report_deleted_success') {
        $status_message = '<div class="alert success">Daily report deleted successfully!</div>';
    } elseif ($_GET['status'] === 'report_deleted_error') {
        $error_details = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error deleting daily report. Please try again.';
        $status_message = '<div class="alert error">' . $error_details . '</div>';
    } elseif ($_GET['status'] === 'material_deducted_success') {
        $status_message = '<div class="alert success">Material quantity successfully deducted from inventory!</div>';
    } elseif ($_GET['status'] === 'material_deducted_error') {
        $error_details = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error deducting material. Please try again.';
        $status_message = '<div class="alert error">' . $error_details . '</div>';
    }
}
// Fallback to SESSION message if no GET status
if (empty($status_message) && isset($_SESSION['status_message'])) {
    $status_message = $_SESSION['status_message'];
    unset($_SESSION['status_message']); // Clear message after displaying
}


include 'includes/header.php';
?>

<style>
/* Re-use main-content-wrapper and alert styles from global or view_project.php */
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

/* Control bar for filters and sorting */
.reports-control-bar {
    display: flex;
    justify-content: space-between; /* Spreads items evenly */
    align-items: center;
    flex-wrap: wrap;
    gap: 15px; /* Space between filter groups */
    background-color: #f8f9fa; /* Lighter background for the control bar */
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
    white-space: nowrap; /* Prevent labels from wrapping */
}

.filter-group select, .sort-group select {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 0.9em;
    cursor: pointer;
    background-color: #fff;
    min-width: 150px; /* Ensure a minimum width for readability */
    flex-grow: 1; /* Allow selects to grow if space is available */
}
.filter-group button { /* Only target the "Apply Filters" button here */
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
.filter-group button:hover {
    background-color: #0056b3;
}

.search-bar-reports {
    display: flex;
    align-items: center;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 5px 10px;
    width: 300px; /* Fixed width, adjust as needed or use max-width: 100% with flex-grow */
    max-width: 100%;
    background-color: #fff;
    box-sizing: border-box; /* Include padding and border in width */
}
.search-bar-reports input {
    border: none;
    outline: none;
    width: 100%;
    padding: 5px;
    font-size: 0.9em;
    background-color: transparent;
}
.search-bar-reports button { /* Specific styles for the search button */
    padding: 5px 10px;
    border-radius: 20px; /* Make it circular-ish */
    width: auto;
    height: auto;
    font-size: 0.8em;
    background-color: #007bff; /* Blue background for the search button */
    color: white;
    margin-left: 5px;
}
.search-bar-reports button:hover {
    background-color: #0056b3;
}


/* Table Specific Styles */
.reports-table-container {
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    max-height: calc(100vh - 300px); /* Adjust height for scrolling content */
    overflow-y: auto; /* Vertical scroll for the container */
    overflow-x: hidden; /* Prevent horizontal scroll for the container */
    padding: 20px;
}
.sticky-header-table {
    width: 100%; /* Make table fill its container */
    border-collapse: collapse;
    table-layout: fixed; /* Keep fixed layout for consistent column widths */
    /* REMOVED: min-width: 1100px; to prevent horizontal scrolling */
}
.sticky-header-table thead {
    position: sticky;
    top: 0;
    z-index: 5;
    background-color: #e9edf2;
}
.sticky-header-table th,
.sticky-header-table td {
    padding: 10px 8px;
    vertical-align: top;
    white-space: normal;
    word-wrap: break-word; /* Ensure long words break */
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

/* Column Widths (Adjusted for no horizontal scroll - sum to 100%) */
.sticky-header-table th:nth-child(1), .sticky-header-table td:nth-child(1) { width: 8%; } /* Date */
.sticky-header-table th:nth-child(2), .sticky-header-table td:nth-child(2) { width: 8%; } /* Time */
.sticky-header-table th:nth-child(3), .sticky-header-table td:nth-child(3) { width: 7%; } /* Status */
.sticky-header-table th:nth-child(4), .sticky-header-table td:nth-child(4) { width: 18%; } /* Description */
.sticky-header-table th:nth-child(5), .sticky-header-table td:nth-child(5) { width: 18%; } /* Materials Used */
.sticky-header-table th:nth-child(6), .sticky-header-table td:nth-child(6) { width: 10%; } /* Reporter */
.sticky-header-table th:nth-child(7), .sticky-header-table td:nth-child(7) { width: 7%; text-align: center; } /* Proof */
.sticky-header-table th:nth-child(8), .sticky-header-table td:nth-child(8) { width: 12%; } /* Project */
.sticky-header-table th:nth-child(9), .sticky-header-table td:nth-child(9) { width: 12%; text-align: center; } /* Actions */


/* Report Status Badge */
.report-status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: bold;
    color: white;
    text-transform: uppercase;
    white-space: nowrap;
    display: inline-block; /* Ensure padding applies correctly */
}
.report-status-badge.pending { background-color: #ffc107; color: #333; }
.report-status-badge.ongoing { background-color: #007bff; }
.report-status-badge.complete { background-color: #28a745; }

/* Materials Used List */
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
    flex-wrap: wrap; /* Allow wrapping if content is too long */
}
.material-usage-list li:last-child {
    border-bottom: none;
}
.material-usage-list .quantity-display {
    white-space: nowrap; /* Keep qty and unit together */
    font-weight: 500;
    color: #333;
    flex-shrink: 0; /* Prevent from shrinking */
}
.material-usage-list .material-name {
    flex-grow: 1; /* Take remaining space */
    margin-left: 5px;
}

/* Deduction specific styles */
.deduct-btn {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 3px 6px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.7em;
    white-space: nowrap;
    transition: background-color 0.2s ease;
    margin-left: 5px;
    flex-shrink: 0; /* Prevent from shrinking */
}
.deduct-btn:hover {
    background-color: #0056b3;
}
.deducted-status {
    color: #28a745;
    font-weight: bold;
    font-size: 0.7em;
    white-space: nowrap;
    margin-left: 5px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    flex-shrink: 0; /* Prevent from shrinking */
}
.deducted-status i {
    font-size: 0.9em;
}
.deducted-info {
    font-size: 0.65em;
    color: #888;
    display: block;
    margin-top: 1px;
    white-space: normal;
    line-height: 1.1;
}


/* Actions cell buttons */
.action-buttons-group {
    display: flex;
    flex-direction: column; /* Stack buttons vertically */
    gap: 5px; /* Space between the stacked buttons */
    align-items: center; /* Center buttons within their group */
}
.actions-cell a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-weight: bold;
    transition: background-color 0.2s ease, transform 0.1s ease;
    padding: 5px 8px;
    font-size: 0.8em;
    border-radius: 5px;
    min-width: 55px; /* Ensure sufficient width */
}
.actions-cell a:hover {
    transform: translateY(-1px);
}
.actions-cell .btn-edit {
    background-color: #ffc107;
    color: #333;
}
.actions-cell .btn-edit:hover {
    background-color: #e0a800;
}
.actions-cell .btn-danger {
    background-color: #dc3545;
    color: white;
}
.actions-cell .btn-danger:hover {
    background-color: #c82333;
}

/* No data message styling */
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

<div class="main-content-wrapper">
    <?php echo $status_message; ?>

    <div class="section-header">
        <h3>Construction Development Monitoring</h3>
    </div>

    <!-- Consolidated Form for Search, Filters, and Sort -->
    <form action="development_monitoring.php" method="GET" id="reportsFilterForm">
        <div class="reports-control-bar">
            <div class="search-bar-reports">
                <input type="text" name="query" placeholder="Search report, project, constructor..." value="<?= htmlspecialchars($search_query); ?>">
                <button type="submit">🔍</button>
            </div>

            <div class="filter-group">
                <label for="filter-project">Project:</label>
                <select id="filter-project" name="project_id">
                    <option value="">All Projects</option>
                    <?php foreach ($projects_for_filter as $proj): ?>
                        <option value="<?= $proj['id'] ?>" <?= ($filter_project_id == $proj['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($proj['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="filter-status">Status:</label>
                <select id="filter-status" name="status">
                    <option value="">All Statuses</option>
                    <option value="ongoing" <?= ($filter_status == 'ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="complete" <?= ($filter_status == 'complete') ? 'selected' : ''; ?>>Complete</option>
                    <option value="pending" <?= ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                </select>

                <label for="filter-constructor">Constructor:</label>
                <select id="filter-constructor" name="constructor_id">
                    <option value="">All Constructors</option>
                    <?php foreach ($constructors_for_filter as $con): ?>
                        <option value="<?= $con['id'] ?>" <?= ($filter_constructor_id == $con['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($con['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Apply Filters</button> <!-- This button now submits the reportsFilterForm -->
            </div>

            <div class="sort-group">
                <label for="sort-reports">Sort by:</label>
                <select id="sort-reports" name="sort">
                    <option value="date_desc" <?= ($sort_column == 'cr.report_date, cr.start_time' && $sort_order == 'DESC') ? 'selected' : ''; ?>>Date (Newest First)</option>
                    <option value="date_asc" <?= ($sort_column == 'cr.report_date, cr.start_time' && $sort_order == 'ASC') ? 'selected' : ''; ?>>Date (Oldest First)</option>
                    <option value="project_asc" <?= ($sort_column == 'p.name' && $sort_order == 'ASC') ? 'selected' : ''; ?>>Project (A-Z)</option>
                    <option value="project_desc" <?= ($sort_column == 'p.name' && $sort_order == 'DESC') ? 'selected' : ''; ?>>Project (Z-A)</option>
                    <option value="status_asc" <?= ($sort_column == 'cr.status' && $sort_order == 'ASC') ? 'selected' : ''; ?>>Status (A-Z)</option>
                    <option value="status_desc" <?= ($sort_column == 'cr.status' && $sort_order == 'DESC') ? 'selected' : ''; ?>>Status (Z-A)</option>
                    <option value="reporter_asc" <?= ($sort_column == 'u.username' && $sort_order == 'ASC') ? 'selected' : ''; ?>>Reporter (A-Z)</option>
                    <option value="reporter_desc" <?= ($sort_column == 'u.username' && $sort_order == 'DESC') ? 'selected' : ''; ?>>Reporter (Z-A)</option>
                </select>
            </div>
        </div>
    </form>


    <div class="reports-table-container">
        <?php if (!empty($reports)): ?>
            <table class="sticky-header-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Description</th>
                        <th>Materials Used</th>
                        <th>Reporter</th>
                        <th class="text-center">Proof</th>
                        <th>Project</th>
                        <?php if (is_admin()): ?><th class="text-center">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report):
                        // Fetch material usages for each report here (outside the loop for $reports_result initially)
                        $material_usages = [];
                        $stmt_usage = $conn->prepare("SELECT rmu.id AS rmu_id, rmu.quantity_used, rmu.is_deducted, rmu.deducted_at,
                                                             m.name AS material_name, m.unit_of_measurement, u.username AS deducted_by_username
                                                      FROM report_material_usage rmu
                                                      JOIN materials m ON rmu.material_id = m.id
                                                      LEFT JOIN users u ON rmu.deducted_by_user_id = u.id
                                                      WHERE rmu.report_id = ?");
                        $stmt_usage->bind_param("i", $report['id']);
                        $stmt_usage->execute();
                        $usage_result = $stmt_usage->get_result();
                        while ($usage_row = $usage_result->fetch_assoc()) {
                            $material_usages[] = $usage_row;
                        }
                        $stmt_usage->close();
                    ?>
                    <tr>
                        <td><?= date('m-d-Y', strtotime($report['report_date'])) ?></td>
                        <td><?= date('h:i A', strtotime($report['start_time'])) ?> - <?= date('h:i A', strtotime($report['end_time'])) ?></td>
                        <td><span class="report-status-badge <?= strtolower($report['status']); ?>"><?= htmlspecialchars($report['status']) ?></span></td>
                        <td><?= htmlspecialchars($report['description']) ?></td>
                        <td>
                            <?php if (!empty($material_usages)): ?>
                                <ul class="material-usage-list">
                                    <?php foreach ($material_usages as $usage): ?>
                                        <li>
                                            <span class="quantity-display"><?= htmlspecialchars(number_format($usage['quantity_used'], 0)) ?> <?= htmlspecialchars($usage['unit_of_measurement']) ?></span>
                                            <span class="material-name">of <?= htmlspecialchars($usage['material_name']) ?></span>
                                            <?php if (is_admin()): ?>
                                                <?php if (!$usage['is_deducted']): ?>
                                                    <button type="button" class="deduct-btn" onclick="deductMaterial(<?= $usage['rmu_id'] ?>, <?= $report['project_id'] ?>)">Deduct</button>
                                                <?php else: ?>
                                                    <span class="deducted-status">Deducted <i class="fas fa-check-circle"></i>
                                                        <?php if ($usage['deducted_by_username'] && $usage['deducted_at']): ?>
                                                            <span class="deducted-info">by <?= htmlspecialchars($usage['deducted_by_username']) ?> on <?= date('m-d-Y h:i A', strtotime($usage['deducted_at'])) ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: // Constructor view ?>
                                                <?php if ($usage['is_deducted']): ?>
                                                    <span class="deducted-status">Deducted <i class="fas fa-check-circle"></i></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($report['reporter_name']) ?></td>
                        <td class="text-center">
                            <?php if (!empty($report['proof_image'])): ?>
                                <a href="<?= htmlspecialchars($report['proof_image']) ?>" target="_blank">View Image</a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td><a href="view_project.php?id=<?= htmlspecialchars($report['project_id']) ?>"><?= htmlspecialchars($report['project_name']) ?></a></td>
                        <?php if (is_admin()): ?>
                        <td class="actions-cell">
                            <div class="action-buttons-group">
                                <a href="edit_development_report.php?id=<?= $report['id'] ?>" class="btn btn-edit">Edit</a>
                                <a href="delete_development_report.php?id=<?= $report['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this report?')">Delete</a>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data-message">No development reports found<?= (!empty($search_query) ? ' matching "' . htmlspecialchars($search_query) . '"' : '') ?>.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// JavaScript for main filter/sort/search form submission
document.addEventListener('DOMContentLoaded', function() {
    const reportsFilterForm = document.getElementById('reportsFilterForm');

    if (reportsFilterForm) {
        reportsFilterForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent default form submission

            const url = new URL(window.location.origin + window.location.pathname); // Base URL without current query params

            // Collect values from all form elements
            const query = reportsFilterForm.querySelector('input[name="query"]').value.trim();
            if (query) url.searchParams.set('query', query);

            const projectId = reportsFilterForm.querySelector('select[name="project_id"]').value;
            if (projectId) url.searchParams.set('project_id', projectId);

            const status = reportsFilterForm.querySelector('select[name="status"]').value;
            if (status) url.searchParams.set('status', status);

            const constructorId = reportsFilterForm.querySelector('select[name="constructor_id"]').value;
            if (constructorId) url.searchParams.set('constructor_id', constructorId);

            const sort = reportsFilterForm.querySelector('select[name="sort"]').value;
            if (sort) url.searchParams.set('sort', sort);

            window.location.href = url.toString();
        });
    }

    // NEW: JavaScript function to handle material deduction via AJAX (from view_project.php)
    function deductMaterial(rmuId, projectId) {
        if (!confirm('Are you sure you want to deduct this material quantity from inventory? This action cannot be undone.')) {
            return; // Stop if user cancels
        }

        const formData = new FormData();
        formData.append('rmu_id', rmuId);
        formData.append('project_id', projectId); // For redirection and logging

        fetch('process_deduct_material_usage.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirect back to this page with a success status
                window.location.href = `development_monitoring.php?status=material_deducted_success&project_id=${projectId}`; // Added project_id to status
            } else {
                alert('Error deducting material: ' + data.message);
                // Optionally, reload the page to reflect any changes if an error occurred server-side
                // window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while attempting to deduct material.');
            // window.location.reload();
        });
    }
    // Make deductMaterial function globally accessible if needed by onclick attribute
    window.deductMaterial = deductMaterial;


    // JavaScript to make alert messages disappear after a few seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade-out'); // Add fade-out class
            // After transition, remove element from DOM
            alert.addEventListener('transitionend', () => {
                alert.remove();
            });
        }, 5000); // 5000 milliseconds = 5 seconds
    });
});
</script>