<?php
include 'includes/db.php';
include 'includes/header.php';

// 1. Get the Project ID from the URL and validate it
$project_id = 0;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $project_id = intval($_GET['id']);
} else {
    die("Invalid Project ID."); // Stop if ID is not valid
}

// 2. Fetch the Project's details
$stmt_project = $conn->prepare("SELECT name FROM projects WHERE id = ?");
$stmt_project->bind_param("i", $project_id);
$stmt_project->execute();
$project_result = $stmt_project->get_result();
if ($project_result->num_rows === 0) {
    die("Project not found.");
}
$project = $project_result->fetch_assoc();
$project_name = $project['name'];

// 3. Fetch all materials linked to this Project ID
$stmt_materials = $conn->prepare("SELECT name, price, supplier, quantity, total_amount FROM materials WHERE project_id = ?");
$stmt_materials->bind_param("i", $project_id);
$stmt_materials->execute();
$materials_result = $stmt_materials->get_result();

?>
<style>
    .materials-container { max-width: 900px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; }
    h1 { border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background-color: #f7f7f7; }
</style>

<div class="materials-container">
    <h1>Materials for: <?= htmlspecialchars($project_name) ?></h1>
    <a href="dashboard.php">← Back to Dashboard</a>

    <table>
        <thead>
            <tr>
                <th>Material Name</th>
                <th>Supplier</th>
                <th>Quantity</th>
                <th>Price per Unit</th>
                <th>Total Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($materials_result && $materials_result->num_rows > 0): ?>
                <?php while ($row = $materials_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['supplier']) ?></td>
                        <td><?= htmlspecialchars($row['quantity']) ?></td>
                        <td><?= htmlspecialchars($row['price']) ?></td>
                        <td><?= htmlspecialchars($row['total_amount']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center; padding: 20px;">No materials have been assigned to this project yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>