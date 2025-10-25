<?php
// ============================================================
// dashboard.php — Updated version (uses units instead of constructor_id)
// ============================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

date_default_timezone_set('Asia/Manila');

// --- Fetch summary counts ---
$total_ongoing = $conn->query("SELECT COUNT(*) AS total FROM projects WHERE status = 'Ongoing'")->fetch_assoc()['total'];
$total_completed = $conn->query("SELECT COUNT(*) AS total FROM projects WHERE status = 'Completed'")->fetch_assoc()['total'];
$total_units = $conn->query("SELECT SUM(units) AS total FROM projects")->fetch_assoc()['total'] ?? 0;

// --- Recent projects list ---
$query = "
    SELECT p.id, p.name, p.location, p.units, p.status, p.created_at
    FROM projects p
    ORDER BY p.id DESC
    LIMIT 5
";
$projects = $conn->query($query);

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== Dashboard Layout ===== */
.dashboard-container {
    padding: 25px;
    background-color: #f8fafc;
    min-height: 100vh;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 25px 30px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    text-align: center;
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-3px);
}
.stat-card h3 {
    margin: 0;
    font-size: 20px;
    color: #374151;
}
.stat-card p {
    font-size: 34px;
    font-weight: 700;
    margin: 5px 0 0;
    color: #111827;
}

.table-card {
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

.table-card h3 {
    margin-bottom: 15px;
    font-size: 22px;
    color: #111827;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th, .table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.table th {
    background-color: #f3f4f6;
    font-weight: 600;
    color: #374151;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    color: #fff;
    font-weight: 600;
}
.status-badge.Pending { background-color: #fbbf24; }
.status-badge.Ongoing { background-color: #3b82f6; }
.status-badge.Completed { background-color: #22c55e; }

.view-all {
    text-align: right;
    margin-top: 10px;
}
.view-all a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
}
.view-all a:hover {
    text-decoration: underline;
}
</style>

<div class="dashboard-container">
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Ongoing Projects</h3>
            <p><?= $total_ongoing ?></p>
        </div>
        <div class="stat-card">
            <h3>Completed Projects</h3>
            <p><?= $total_completed ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Units / Houses</h3>
            <p><?= $total_units ?></p>
        </div>
    </div>

    <div class="table-card">
        <h3>Recent Projects</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Project Name</th>
                    <th>Location</th>
                    <th>Units</th>
                    <th>Status</th>
                    <th>Start Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects->num_rows > 0): ?>
                    <?php while ($p = $projects->fetch_assoc()): ?>
                        <tr>
                            <td><a href="modules/view_project.php?id=<?= $p['id'] ?>" style="color:#2563eb;text-decoration:none;font-weight:600;"><?= htmlspecialchars($p['name']) ?></a></td>
                            <td><?= htmlspecialchars($p['location']) ?></td>
                            <td><?= htmlspecialchars($p['units']) ?></td>
                            <td><span class="status-badge <?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars($p['status']) ?></span></td>
                            <td><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center;color:#6b7280;">No projects found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="view-all">
            <a href="uploads/projects.php">View All Projects →</a>
        </div>
    </div>
</div>

</div> <!-- closes main-content -->
</body>
</html>
