<?php
// The header.php file now handles starting the session and all security checks.
include 'includes/db.php';
include 'includes/header.php'; // This includes your sidebar and top header bar

// --- PHP LOGIC TO GET DATA FOR THE DASHBOARD ---

// 1. Count Ongoing Projects
$ongoing_stmt = $conn->prepare("SELECT COUNT(id) AS ongoing_count FROM projects WHERE status = 'Ongoing'");
$ongoing_stmt->execute();
$ongoing_count = $ongoing_stmt->get_result()->fetch_assoc()['ongoing_count'];

// 2. Count Completed Projects
$completed_stmt = $conn->prepare("SELECT COUNT(id) AS completed_count FROM projects WHERE status = 'Completed'");
$completed_stmt->execute();
$completed_count = $completed_stmt->get_result()->fetch_assoc()['completed_count'];

// 3. Get Active Constructors
$constructors_stmt = $conn->prepare("SELECT username FROM users WHERE role = 'constructor' ORDER BY username ASC");
$constructors_stmt->execute();
$constructors_result = $constructors_stmt->get_result();
$constructors_list = [];
while ($row = $constructors_result->fetch_assoc()) {
    $constructors_list[] = $row['username'];
}
$active_constructors_count = count($constructors_list);

// 4. Get 3 Most Recent Projects for the table
$query = "SELECT p.id, p.name, p.created_at, p.status, u.username AS constructor_name FROM projects AS p JOIN users AS u ON p.constructor_id = u.id ORDER BY p.created_at DESC LIMIT 3";
$projects_result = $conn->query($query);
?>

<!-- 
    The <div class="main-content"> was already opened in your 'includes/header.php' file.
    We just need to add the content that goes inside it.
-->

<!-- CSS specific to the dashboard's content -->
<style>
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 30px;
        margin-bottom: 40px;
    }
    .stat-card {
        background-color: #ffffff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
    }
    .stat-card h3 {
        margin: 0 0 10px;
        font-size: 16px;
        color: #555;
        font-weight: 600;
    }
    .stat-card .count {
        font-size: 36px;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 15px;
    }
    .constructors-list {
        list-style: none;
        padding: 0;
        margin: 0;
        overflow-y: auto;
        max-height: 150px;
    }
    .constructors-list li {
        background-color: #f5f6fa;
        padding: 8px 12px;
        border-radius: 6px;
        margin-bottom: 5px;
        font-size: 14px;
        color: #333;
    }
    .projects-section {
        background-color: #ffffff;
        padding: 25px 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        width: 96%;
    }
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .section-title {
        margin: 0;
        font-size: 20px;
    }
    .view-all-link {
        text-decoration: none;
        color: #007bff;
        font-weight: bold;
    }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; }
    thead { background-color: #f7f7f7; }
    th { font-weight: bold; text-transform: uppercase; border-bottom: 2px solid #ddd; }
    tbody tr:hover { background-color: #f5f8ff; }
    .status-badge { color: #ffffff; padding: 8px 18px; border-radius: 15px; font-size: 12px; font-weight: bold; text-transform: capitalize; }
    .status-badge.ongoing { background-color: #007bff; }
    .status-badge.completed { background-color: #28a745; }
    .status-badge.pending { background-color: #ffc107; color: #333; }
</style>

<!-- First row of content: The three statistics cards -->
<div class="stats-container">
    <div class="stat-card">
        <h3>Ongoing Projects</h3>
        <p class="count"><?= $ongoing_count ?></p>
    </div>
    <div class="stat-card">
        <h3>Completed Projects</h3>
        <p class="count"><?= $completed_count ?></p>
    </div>
    <div class="stat-card">
        <h3>Active Constructors (<?= $active_constructors_count ?>)</h3>
        <ul class="constructors-list">
            <?php if (!empty($constructors_list)): ?>
                <?php foreach ($constructors_list as $constructor): ?>
                    <li><?= htmlspecialchars($constructor) ?></li>
                <?php endforeach; ?>
            <?php else: ?>
                <li>No constructors found.</li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<!-- Second row of content: The Recent Projects table -->
<div class="projects-section">
    <div class="section-header">
        <h2 class="section-title">Recent Projects</h2>
        <a href="projects.php" class="view-all-link">View All Projects →</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>PROJECT</th>
                <th>DATE</th>
                <th>CONSTRUCTOR</th>
                <th>STATUS</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($projects_result && $projects_result->num_rows > 0): ?>
                <?php while ($row = $projects_result->fetch_assoc()): ?>
                    <tr>
                        <td><a href="view_project.php?id=<?= $row['id'] ?>" style="font-weight: bold; color: #0056b3; text-decoration:none;"><?= htmlspecialchars($row['name']) ?></a></td>
                        <td><?= date('F j, Y', strtotime($row['created_at'])) ?></td>
                        <td><?= htmlspecialchars($row['constructor_name']) ?></td>
                        <td>
                            <?php $status_class = strtolower($row['status']); ?>
                            <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($row['status']) ?></span>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center; padding: 20px;">No recent projects found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// This includes the closing tags (</div>, </body>, </html>) from your footer file
include 'includes/footer.php'; 
?>