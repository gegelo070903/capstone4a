<?php
session_start();
include 'includes/db.php';
include 'includes/header.php';

// --- Security and Data Fetching ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// 1. Get Project ID from URL
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($project_id === 0) { die("Invalid Project ID."); }

// 2. Authorize User (Admin can see all, Constructor can only see their own)
if ($_SESSION['user_role'] !== 'admin') {
    $auth_stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND constructor_id = ?");
    $auth_stmt->bind_param("ii", $project_id, $_SESSION['user_id']);
    $auth_stmt->execute();
    if ($auth_stmt->get_result()->num_rows === 0) {
        die("Access Denied: You are not assigned to this project.");
    }
}

// 3. Fetch Project Details
$project_stmt = $conn->prepare("SELECT p.name, p.created_at, p.status, u.username AS constructor_name FROM projects p JOIN users u ON p.constructor_id = u.id WHERE p.id = ?");
$project_stmt->bind_param("i", $project_id);
$project_stmt->execute();
$project = $project_stmt->get_result()->fetch_assoc();
if (!$project) { die("Project not found."); }

// 4. Fetch Materials for this Project
$materials_result = $conn->query("SELECT * FROM materials WHERE project_id = $project_id ORDER BY name ASC");
?>

<style>
/* Project Summary Header */
.project-summary-header {
    background-color: #fff;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.07);
}
.project-summary-header h1 { font-size: 28px; margin: 0 0 10px 0; font-weight: 700; color: #2c3e50; }
.project-summary-header p { margin: 0; color: #555; }

/* Materials Section */
.materials-section {
    background-color: #fff;
    padding: 25px 30px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.07);
}
.materials-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.materials-header h2 { margin: 0; font-size: 22px; }
.btn-success { background-color: #28a745; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 16px; border: none; cursor: pointer; }
.btn-success:hover { background-color: #218838; }

/* Materials Table */
table{width:100%;border-collapse:collapse}th,td{padding:15px;text-align:left;border-bottom:1px solid #f0f0f0}thead{background-color:#f7f7f7}th{font-weight:bold;border-bottom:2px solid #ddd;text-transform:uppercase}.action-links a{margin-right:15px;text-decoration:none;font-weight:bold}.edit-link{color:#007bff}.delete-link{color:#dc3545}
</style>

<!-- Project Summary Details -->
<div class="project-summary-header">
    <h1><?= htmlspecialchars($project['name']) ?></h1>
    <p><strong>Constructor:</strong> <?= htmlspecialchars($project['constructor_name']) ?> | <strong>Status:</strong> <?= htmlspecialchars($project['status']) ?></p>
</div>

<!-- Materials Management Section -->
<div class="materials-section">
    <div class="materials-header">
        <h2>Project Materials</h2>
        <!-- This button links to your existing add_materials.php -->
        <a href="add_materials.php?project_id=<?= $project_id ?>" class="btn-success">Add Material</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>MATERIAL</th>
                <th>QUANTITY</th>
                <th>SUPPLIER</th>
                <th>PRICE</th>
                <th>TOTAL</th>
                <th>ACTIONS</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($materials_result && $materials_result->num_rows > 0): ?>
                <?php while($row = $materials_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['quantity']) ?></td>
                        <td><?= htmlspecialchars($row['supplier']) ?></td>
                        <td>₱<?= number_format($row['price'], 2) ?></td>
                        <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                        <td class="action-links">
                            <!-- Links to your existing edit and delete scripts -->
                            <a href="edit_materials.php?id=<?= $row['id'] ?>&project_id=<?= $project_id ?>" class="edit-link">Edit</a>
                            <a href="delete_materials.php?id=<?= $row['id'] ?>&project_id=<?= $project_id ?>" class="delete-link" onclick="return confirm('Are you sure you want to delete this material?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center; padding: 20px;">No materials have been added to this project yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>