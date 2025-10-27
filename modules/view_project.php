<?php
// ======================================
// view_project.php — Final Version
// ======================================

include '../includes/db.php';
include '../includes/functions.php';

require_login();
if (!is_admin()) {
    die('<h3 style="color:red;">Access denied. Only admins can view projects.</h3>');
}

date_default_timezone_set('Asia/Manila');

// ✅ Get project ID safely
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('<h3 style="color:red;">Invalid project ID.</h3>');
}
$project_id = intval($_GET['id']);

// ✅ Fetch project details
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    die('<h3 style="color:red;">Project not found.</h3>');
}

// ✅ Fetch all units for this project
$stmt_units = $conn->prepare("SELECT * FROM project_units WHERE project_id = ?");
$stmt_units->bind_param("i", $project_id);
$stmt_units->execute();
$units_result = $stmt_units->get_result();

// ✅ Count total units and completed progress
$total_units = $units_result->num_rows;
$total_progress = 0;

$units = [];
while ($row = $units_result->fetch_assoc()) {
    $units[] = $row;
    $total_progress += intval($row['progress']);
}

$overall_progress = $total_units > 0 ? round($total_progress / $total_units) : 0;

// ✅ Auto-update project status based on progress
if ($overall_progress >= 100) {
    $new_status = "Completed";
} elseif ($overall_progress > 0) {
    $new_status = "Ongoing";
} else {
    $new_status = "Pending";
}

$conn->query("UPDATE projects SET status='$new_status' WHERE id=$project_id");

include '../includes/header.php';
?>

<div class="main-content-wrapper">
    <div class="project-header-card">
        <h2><?= htmlspecialchars($project['name']); ?></h2>
        <p><strong>Status:</strong> 
            <span class="status <?= strtolower($project['status']); ?>"><?= htmlspecialchars($project['status']); ?></span> |
            <strong>Location:</strong> <?= htmlspecialchars($project['location']); ?> |
            <strong>Units:</strong> <?= $project['units']; ?> |
            <strong>Created:</strong> <?= date('M d, Y', strtotime($project['created_at'])); ?>
        </p>

        <div class="progress-container">
            <div class="progress-bar" style="width: <?= $overall_progress; ?>%;"><?= $overall_progress; ?>%</div>
        </div>

        <div class="project-actions">
            <a href="../checklist/add_checklist_item.php?project_id=<?= $project_id; ?>" class="btn btn-primary">
                <i class="fas fa-tasks"></i> Add Checklist
            </a>
            <a href="edit_project.php?id=<?= $project_id; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit Project
            </a>
            <a href="delete_project.php?id=<?= $project_id; ?>" class="btn btn-danger" onclick="return confirm('Move this project to trash?');">
                <i class="fas fa-trash"></i> Delete
            </a>
        </div>
    </div>

    <div class="units-section">
        <h3>🏘️ Project Units</h3>

        <?php if (count($units) > 0): ?>
            <?php foreach ($units as $unit): ?>
                <div class="unit-card">
                    <h4><?= htmlspecialchars($unit['name']); ?></h4>
                    <p><?= !empty($unit['description']) ? htmlspecialchars($unit['description']) : "No description"; ?></p>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?= $unit['progress']; ?>%;">
                            <?= $unit['progress']; ?>%
                        </div>
                    </div>
                    <div class="unit-actions">
                        <a href="../checklist/view_checklist.php?unit_id=<?= $unit['id']; ?>&project_id=<?= $project_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-list-check"></i> View Checklist
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="no-units">No units defined yet for this project.</p>
        <?php endif; ?>
    </div>
</div>

<style>
.main-content-wrapper {
    padding: 20px;
    background: #f8f9fc;
}

.project-header-card {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}

.status {
    padding: 5px 12px;
    border-radius: 8px;
    font-weight: 600;
    color: #fff;
}
.status.pending { background-color: #ffc107; }
.status.ongoing { background-color: #17a2b8; }
.status.completed { background-color: #28a745; }

.progress-container {
    width: 100%;
    height: 22px;
    background: #e9ecef;
    border-radius: 12px;
    margin: 10px 0;
    overflow: hidden;
}
.progress-bar {
    height: 100%;
    text-align: center;
    color: #fff;
    background: #007bff;
    line-height: 22px;
    font-size: 13px;
    transition: width 0.4s ease;
}

.project-actions a {
    margin-right: 10px;
}

.units-section {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.unit-card {
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
    background-color: #fafafa;
}

.unit-card h4 {
    margin: 0 0 10px;
    font-size: 1.1em;
    color: #333;
}

.unit-actions {
    margin-top: 10px;
}

.btn {
    text-decoration: none;
    padding: 8px 14px;
    border-radius: 6px;
    display: inline-block;
    font-weight: 600;
}
.btn-primary { background: #007bff; color: #fff; }
.btn-warning { background: #ffc107; color: #212529; }
.btn-danger { background: #dc3545; color: #fff; }
.btn-outline-primary {
    border: 1px solid #007bff;
    color: #007bff;
    background: transparent;
}
.btn-outline-primary:hover {
    background: #007bff;
    color: #fff;
}
.no-units {
    color: #888;
    font-style: italic;
    text-align: center;
}
</style>
