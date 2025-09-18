<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

date_default_timezone_set('Asia/Manila');

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !is_admin()) {
    $_SESSION['status_message'] = '<div class="alert error">Unauthorized access to full reports.</div>';
    header('Location: dashboard.php');
    exit();
}

// --- PHP Logic for Search/Filter/Sort for the project list ---
$search_query = "";
$sort_column = "p.name"; // Default sort
$sort_order = "ASC";

$where_clauses = [];
$param_types = "";
$param_values = [];

// Handle Search Query for project name or constructor name
if (isset($_GET['query']) && !empty(trim($_GET['query']))) {
    $search_query = trim($_GET['query']);
    $where_clauses[] = "(p.name LIKE ? OR u.username LIKE ?)";
    $param_types .= "ss";
    $param_values[] = "%$search_query%";
    $param_values[] = "%$search_query%";
}

// Handle Sorting
if (isset($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'name_asc':
            $sort_column = "p.name";
            $sort_order = "ASC";
            break;
        case 'name_desc':
            $sort_column = "p.name";
            $sort_order = "DESC";
            break;
        case 'date_asc':
            $sort_column = "p.created_at";
            $sort_order = "ASC";
            break;
        case 'date_desc':
            $sort_column = "p.created_at";
            $sort_order = "DESC";
            break;
        case 'status_asc':
            $sort_column = "p.status";
            $sort_order = "ASC";
            break;
        case 'status_desc':
            $sort_column = "p.status";
            $sort_order = "DESC";
            break;
    }
}

// Base query to fetch all projects
$sql = "SELECT p.id, p.name, p.location, p.created_at, p.status, u.username AS constructor_name
        FROM projects p
        LEFT JOIN users u ON p.constructor_id = u.id";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY {$sort_column} {$sort_order}";

$stmt_projects = $conn->prepare($sql);
if (!empty($param_types)) {
    $stmt_projects->bind_param($param_types, ...$param_values);
}
$stmt_projects->execute();
$all_projects_data = $stmt_projects->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_projects->close();


// --- Handle Status Messages (similar to other pages) ---
$status_message = '';
if (isset($_SESSION['status_message'])) {
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
    background-color: #f0f2f5; /* Lighter background for the overall page */
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    overflow-x: hidden; /* Ensure this wrapper doesn't cause horizontal scroll */
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
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 10px;
}
.section-header h3 {
    font-size: 2em;
    color: #333;
    margin: 0;
}

/* Control bar for filters and sorting */
.project-control-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    background-color: #ffffff; /* Use white background for the control bar */
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    padding: 15px 25px;
    margin-bottom: 25px;
}

.search-bar-projects {
    display: flex;
    align-items: center;
    border: 1px solid #ddd;
    border-radius: 24px;
    padding: 5px 8px;
    width: 350px;
    max-width: 100%;
    background-color: #f8f9fa;
    flex-shrink: 0;
}

.search-bar-projects input {
    border: none;
    outline: none;
    width: 100%;
    padding: 5px;
    font-size: 14px;
    background-color: transparent;
}
.search-bar-projects button {
    background-color: #007bff;
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 14px;
    flex-shrink: 0;
}
.search-bar-projects button:hover {
    background-color: #0056b3;
}

.sort-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.sort-group label {
    font-weight: 500;
    color: #555;
    font-size: 0.9em;
}

.sort-group select {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 0.9em;
    cursor: pointer;
    background-color: #fff;
    min-width: 180px; /* Adjusted dropdown width */
}


/* Styling for individual project report cards */
.project-report-card {
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    padding: 25px;
    margin-bottom: 30px; /* Space between project cards */
    border: 1px solid #e0e0e0;
}

.project-report-card .project-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}
.project-report-card h2.project-name {
    font-size: 1.8em;
    color: #2c3e50;
    margin: 0; /* Reset margin */
}

.project-report-card .project-info p {
    margin: 3px 0;
    font-size: 0.95em;
    color: #555;
}
.project-report-card .project-info strong {
    color: #333;
}

.project-report-card .subsection-title {
    font-size: 1.4em;
    color: #333;
    margin-top: 30px;
    margin-bottom: 15px;
    padding-bottom: 5px;
    border-bottom: 1px solid #f0f0f0;
}

/* Reused status badge and buttons for consistency */
.status-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; color: white; text-transform: uppercase; white-space: nowrap; display: inline-block; }
.status-badge.ongoing { background-color: #007bff; }
.status-badge.complete { background-color: #28a745; }
.status-badge.pending { background-color: #ffc107; color: #333; }
.status-badge.cancelled { background-color: #dc3545; }

.btn-generate-pdf-individual {
    background-color: #dc3545; /* Red for PDF */
    color: white;
    padding: 8px 15px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    font-size: 0.85em;
    transition: background-color 0.2s ease, transform 0.1s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: none;
    cursor: pointer;
}
.btn-generate-pdf-individual:hover {
    background-color: #c82333;
    transform: translateY(-1px);
}
.btn-generate-pdf-individual i {
    font-size: 1.1em;
}

.no-data-message {
    text-align: center;
    color: #777;
    font-style: italic;
    padding: 20px;
    font-size: 1.0em;
    background-color: #fcfcfc;
    border-radius: 8px;
}

/* Table within each project-report-card */
.full-report-content-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
    table-layout: fixed;
}
.full-report-content-table th, .full-report-content-table td {
    border: 1px solid #eee;
    padding: 8px;
    text-align: left;
    vertical-align: top;
    word-wrap: break-word;
    font-size: 0.85em;
    line-height: 1.2;
}
.full-report-content-table th {
    background-color: #f8f8f8;
    font-weight: bold;
}
.full-report-content-table tbody tr:hover {
    background-color: #f9f9f9;
}
.full-report-content-table .img-preview {
    max-width: 60px; /* Constrain maximum width */
    max-height: 60px; /* Constrain maximum height */
    width: auto; /* Allow width to be automatic based on max-width */
    height: auto; /* Allow height to be automatic based on max-height */
    object-fit: contain; /* Ensure image fits within bounds without cropping */
    display: block; /* Important for margin:auto to work */
    margin: 5px auto 0 auto; /* Center image within cell, with top margin */
    border-radius: 4px;
    border: 1px solid #eee;
}

/* Checklist specific styles for web display */
.project-report-card .checklist-item i {
    margin-right: 8px;
    color: #ccc;
}
.project-report-card .checklist-item.completed i {
    color: #28a745;
}

/* Specific column widths for Materials Acquired Table (sum to 100%) */
.materials-table th:nth-child(1), .materials-table td:nth-child(1) { width: 5%; text-align: center; } /* ID */
.materials-table th:nth-child(2), .materials-table td:nth-child(2) { width: 17%; } /* Name */
.materials-table th:nth-child(3), .materials-table td:nth-child(3) { width: 9%; text-align: right; } /* Quantity */
.materials-table th:nth-child(4), .materials-table td:nth-child(4) { width: 16%; } /* Supplier */
.materials-table th:nth-child(5), .materials-table td:nth-child(5) { width: 10%; text-align: right; } /* Total Value */
.materials-table th:nth-child(6), .materials-table td:nth-child(6) { width: 14%; } /* Date Added */
.materials-table th:nth-child(7), .materials-table td:nth-child(7) { width: 29%; } /* Purpose */

/* Specific column widths for Daily Development Reports Table (sum to 100%) */
.reports-table th:nth-child(1), .reports-table td:nth-child(1) { width: 10%; } /* Date */
.reports-table th:nth-child(2), .reports-table td:nth-child(2) { width: 8%; } /* Time */
.reports-table th:nth-child(3), .reports-table td:nth-child(3) { width: 8%; } /* Status */
.reports-table th:nth-child(4), .reports-table td:nth-child(4) { width: 25%; } /* Description */
.reports-table th:nth-child(5), .reports-table td:nth-child(5) { width: 20%; } /* Materials Used */
.reports-table th:nth-child(6), .reports-table td:nth-child(6) { width: 10%; } /* Reporter */
.reports-table th:nth-child(7), .reports-table td:nth-child(7) { width: 9%; text-align: center; } /* Proof */

</style>

<div class="main-content-wrapper">
    <?php echo $status_message; ?>

    <div class="section-header">
        <h3>Full Project Reports Overview</h3>
    </div>

    <div class="project-control-bar">
        <form action="full_reports.php" method="GET" class="search-bar-projects" id="projectSearchForm">
            <input type="text" name="query" placeholder="Search project name or constructor..." value="<?= htmlspecialchars($search_query); ?>">
            <button type="submit">🔍</button>
        </form>

        <div class="sort-group">
            <label for="sort-projects">Sort by:</label>
            <select id="sort-projects" name="sort" onchange="this.form.submit()">
                <option value="name_asc" <?= ($sort_column == 'p.name' && $sort_order == 'ASC') ? 'selected' : ''; ?>>Project Name (A-Z)</option>
                <option value="name_desc" <?= ($sort_column == 'p.name' && $sort_order == 'DESC') ? 'selected' : ''; ?>>Project Name (Z-A)</option>
                <option value="date_desc" <?= ($sort_column == 'p.created_at' && $sort_order == 'DESC') ? 'selected' : ''; ?>>Date Created (Newest First)</option>
                <option value="date_asc" <?= ($sort_column == 'p.created_at' && $sort_order == 'ASC') ? 'selected' : ''; ?>>Date Created (Oldest First)</option>
                <option value="status_asc" <?= ($sort_column == 'p.status' && $sort_order == 'ASC') ? 'selected' : ''; ?>>Status (A-Z)</option>
                <option value="status_desc" <?= ($sort_column == 'p.status' && $sort_order == 'DESC') ? 'selected' : ''; ?>>Status (Z-A)</option>
            </select>
        </div>
    </div>


    <?php if (!empty($all_projects_data)): ?>
        <?php foreach ($all_projects_data as $project_summary):
            $project_id = $project_summary['id']; // Current project ID in loop

            // --- Fetch full project details (constructor name) for this specific project ---
            $current_project_details = null;
            $stmt_current_project = $conn->prepare("SELECT p.*, u.username AS constructor_name
                                                    FROM projects p
                                                    LEFT JOIN users u ON p.constructor_id = u.id
                                                    WHERE p.id = ?");
            $stmt_current_project->bind_param("i", $project_id);
            $stmt_current_project->execute();
            $result_current_project = $stmt_current_project->get_result();
            $current_project_details = $result_current_project->fetch_assoc();
            $stmt_current_project->close();


            // --- Fetch Checklist Items for THIS Project ---
            $checklist_data = [];
            $total_checklist_items = 0;
            $completed_checklist_items = 0;
            $stmt_checklist = $conn->prepare("SELECT pc.*, u.username AS completed_by_username
                                             FROM project_checklists pc
                                             LEFT JOIN users u ON pc.completed_by_user_id = u.id
                                             WHERE pc.project_id = ? ORDER BY pc.created_at ASC");
            $stmt_checklist->bind_param("i", $project_id);
            $stmt_checklist->execute();
            $result_checklist = $stmt_checklist->get_result();
            while ($item = $result_checklist->fetch_assoc()) {
                $total_checklist_items++;
                if ($item['is_completed']) {
                    $completed_checklist_items++;
                }
                $checklist_data[] = $item;
            }
            $stmt_checklist->close();
            $project_completion_percentage = ($total_checklist_items > 0) ? round(($completed_checklist_items / $total_checklist_items) * 100) : 0;


            // --- Fetch Materials for THIS Project ---
            $materials_for_project_data = [];
            $stmt_project_materials = $conn->prepare("SELECT * FROM materials WHERE project_id = ? ORDER BY name ASC");
            $stmt_project_materials->bind_param("i", $project_id);
            $stmt_project_materials->execute();
            $result_project_materials = $stmt_project_materials->get_result();
            while ($mat = $result_project_materials->fetch_assoc()) {
                $materials_for_project_data[] = $mat;
            }
            $stmt_project_materials->close();


            // --- Fetch Daily Development Reports for THIS Project ---
            $reports_for_project_data = [];
            $stmt_project_reports = $conn->prepare("SELECT cr.*, u.username as reporter_name
                                                    FROM construction_reports cr
                                                    LEFT JOIN users u ON cr.constructor_id = u.id
                                                    WHERE cr.project_id = ? ORDER BY cr.report_date DESC, cr.start_time DESC");
            $stmt_project_reports->bind_param("i", $project_id);
            $stmt_project_reports->execute();
            $result_project_reports = $stmt_project_reports->get_result();
            while ($report_row = $result_project_reports->fetch_assoc()) {
                $reports_for_project_data[] = $report_row;
            }
            $stmt_project_reports->close();
        ?>
            <div class="project-report-card">
                <div class="project-header-row">
                    <h2 class="project-name"><?= htmlspecialchars($current_project_details['name']); ?></h2>
                    <a href="generate_project_pdf.php?id=<?= $project_id ?>" class="btn-generate-pdf-individual" target="_blank">
                        <i class="fas fa-file-pdf"></i> Export to PDF
                    </a>
                </div>
                <div class="project-info">
                    <p><strong>Location:</strong> <?= htmlspecialchars($current_project_details['location']); ?></p>
                    <p><strong>Assigned Constructor:</strong> <?= htmlspecialchars($current_project_details['constructor_name']); ?></p>
                    <p><strong>Date Created:</strong> <?= date('M d, Y', strtotime($current_project_details['created_at'])); ?></p>
                    <p><strong>Current Status:</strong> <span class="status-badge <?= strtolower($current_project_details['status']); ?>"><?= htmlspecialchars($current_project_details['status']) ?></span></p>
                </div>

                <!-- Project Milestones / Checklist -->
                <h4 class="subsection-title">Project Milestones / Checklist</h4>
                <p style="font-size: 0.9em; margin-bottom: 10px;">Overall Progress: <strong><?= $project_completion_percentage ?>%</strong> (<?= $completed_checklist_items ?> of <?= $total_checklist_items ?> completed)</p>
                <?php if ($total_checklist_items > 0): ?>
                    <div class="progress-bar-wrapper" style="height: 12px; margin-bottom: 20px;">
                        <div class="progress-bar" style="width: <?= $project_completion_percentage ?>%; line-height: 12px; font-size: 0.8em;"><?= $project_completion_percentage ?>%</div>
                    </div>
                <?php else: ?>
                    <p class="no-data-message" style="margin-top: 0;">No checklist items added for this project.</p>
                <?php endif; ?>

                <?php if (!empty($checklist_data)): ?>
                    <ul style="list-style: none; padding: 0; margin-top: 10px;">
                        <?php foreach ($checklist_data as $item): ?>
                        <li class="checklist-item <?= $item['is_completed'] ? 'completed' : '' ?>" style="font-size: 0.9em;">
                            <i class="fas <?= $item['is_completed'] ? 'fa-check-square' : 'fa-square' ?>" style="color: <?= $item['is_completed'] ? '#28a745' : '#ccc' ?>; margin-right: 8px;"></i>
                            <?= htmlspecialchars($item['item_description']) ?>
                            <?php if ($item['is_completed'] && $item['completed_by_username']): ?>
                                <span class="checklist-meta" style="font-size: 0.75em; margin-left: 10px;"> (Completed by: <?= htmlspecialchars($item['completed_by_username']) ?> on <?= date('M d, Y h:i A', strtotime($item['completed_at'])) ?>)</span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>


                <!-- Materials Acquired for This Project -->
                <h4 class="subsection-title">Materials Acquired for This Project</h4>
                <div class="table-responsive">
                    <?php if (!empty($materials_for_project_data)): ?>
                        <table class="full-report-content-table materials-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th class="text-right">Quantity</th>
                                    <th>Supplier</th>
                                    <th class="text-right">Total Value</th>
                                    <th>Date Added</th>
                                    <th>Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($materials_for_project_data as $material):
                                    $material_date_str = $material['date'];
                                    $material_time_str = $material['time'];
                                    $formatted_date_time = 'N/A';
                                    if ($material_date_str !== '0000-00-00' && $material_date_str !== null) {
                                        $material_date_timestamp = strtotime($material_date_str);
                                        $formatted_date = date('M d, Y', $material_date_timestamp);
                                        $formatted_time = ($material_time_str) ? date('h:i A', strtotime($material_time_str)) : '';
                                        $formatted_date_time = $formatted_date;
                                        if (!empty($formatted_time) && $formatted_time !== '12:00 AM') {
                                            $formatted_date_time .= ' (' . $formatted_time . ')';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($material['id']) ?></td>
                                    <td><?= htmlspecialchars($material['name']) ?></td>
                                    <td class="text-right">
                                        <div class="qty-unit-display">
                                            <span class="quantity-value"><?= htmlspecialchars(number_format($material['quantity'], 0)) ?></span>
                                            <span class="unit-value"><?= htmlspecialchars($material['unit_of_measurement']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($material['supplier']) ?></td>
                                    <td class="text-right">₱<?= htmlspecialchars(number_format($material['total_amount'], 0)) ?></td>
                                    <td><?= $formatted_date_time ?></td>
                                    <td><?= htmlspecialchars($material['purpose']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-data-message">No materials specifically acquired for this project.</p>
                    <?php endif; ?>
                </div>

                <!-- Daily Development Reports for This Project -->
                <h4 class="subsection-title">Daily Development Reports for This Project</h4>
                <div class="table-responsive">
                    <?php if (!empty($reports_for_project_data)): ?>
                        <table class="full-report-content-table reports-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Description</th>
                                    <th>Materials Used</th>
                                    <th>Reporter</th>
                                    <th class="text-center">Proof</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($reports_for_project_data as $report):
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
                                    <td><span class="status-badge <?= strtolower($report['status']); ?>"><?= htmlspecialchars($report['status']) ?></span></td>
                                    <td><?= htmlspecialchars($report['description']) ?></td>
                                    <td>
                                        <?php if (!empty($material_usages)): ?>
                                            <ul class="material-usage-list">
                                                <?php foreach ($material_usages as $usage): ?>
                                                    <li>
                                                        <span class="quantity-display"><?= htmlspecialchars(number_format($usage['quantity_used'], 0)) ?> <?= htmlspecialchars($usage['unit_of_measurement']) ?></span>
                                                        <span class="material-name">of <?= htmlspecialchars($usage['material_name']) ?></span>
                                                        <?php if ($usage['is_deducted']): ?>
                                                            <span class="deducted-status">Deducted <i class="fas fa-check-circle"></i>
                                                                <?php if ($usage['deducted_by_username'] && $usage['deducted_at']): ?>
                                                                    <span class="deducted-info">by <?= htmlspecialchars($usage['deducted_by_username']) ?> on <?= date('m-d-Y h:i A', strtotime($usage['deducted_at'])) ?></span>
                                                                <?php endif; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($report['reporter_name']) ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($report['proof_image'])): ?>
                                            <img src="<?= htmlspecialchars($report['proof_image']) ?>" class="full-report-content-table img-preview" alt="Proof Image">
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-data-message">No daily development reports for this project.</p>
                    <?php endif; ?>
                </div>
            </div><!-- End project-report-card -->
        <?php endforeach; ?>
    <?php else: ?>
        <p class="no-data-message" style="background-color: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">No projects found in the system to generate full reports.</p>
    <?php endif; ?>

</div> <!-- Closes .main-content-wrapper -->

<?php include 'includes/footer.php'; ?>

<script>
    // JavaScript for search and sort form submission
    document.addEventListener('DOMContentLoaded', () => {
        const projectSearchForm = document.getElementById('projectSearchForm');
        const sortSelect = document.getElementById('sort-projects');

        const updateUrlAndSubmit = () => {
            const url = new URL(window.location.href);
            // Clear existing filter/sort parameters
            url.searchParams.delete('query');
            url.searchParams.delete('sort');

            // Add current search query
            const searchQueryInput = projectSearchForm.querySelector('input[name="query"]');
            if (searchQueryInput && searchQueryInput.value.trim() !== '') {
                url.searchParams.set('query', searchQueryInput.value.trim());
            }

            // Add current sort parameter
            url.searchParams.set('sort', sortSelect.value);

            window.location.href = url.toString();
        };

        if (projectSearchForm) {
            projectSearchForm.addEventListener('submit', function(event) {
                event.preventDefault(); // Prevent default form submission
                updateUrlAndSubmit();
            });
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                updateUrlAndSubmit();
            });
        }

        // JavaScript to make alert messages disappear after a few seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.classList.add('fade-out');
                alert.addEventListener('transitionend', () => {
                    alert.remove();
                });
            }, 5000);
        });
    });
</script>
</body>
</html>