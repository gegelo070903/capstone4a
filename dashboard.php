<?php
// ============================================================
// dashboard.php — Updated version with progress bars
// ============================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

date_default_timezone_set('Asia/Manila');

// --- Fetch summary counts ---
$total_ongoing = $conn->query("SELECT COUNT(*) AS total FROM projects WHERE status = 'Ongoing'")->fetch_assoc()['total'];
$total_completed = $conn->query("SELECT COUNT(*) AS total FROM projects WHERE projects.status = 'Completed'")->fetch_assoc()['total'];
$total_units = $conn->query("SELECT SUM(units) AS total FROM projects")->fetch_assoc()['total'] ?? 0;

// --- Recent projects list (REPLACED WITH NEW QUERY) ---
// Automatically calculate progress based on the latest report in project_reports
$query = "
    SELECT 
        p.id,
        p.name,
        p.location,
        p.units,
        p.status,
        COALESCE((
            SELECT r.progress_percentage
            FROM project_reports r
            WHERE r.project_id = p.id
            ORDER BY r.report_date DESC, r.id DESC
            LIMIT 1
        ), 0) AS progress,
        p.created_at
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
.stat-card:hover { transform: translateY(-3px); }
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

/* ===== Table Section ===== */
.table-card {
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

/* ✅ NEW CSS: Recent Projects Header Row */
.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.table-header h3 {
    font-size: 22px;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

.view-all-link {
    color: #2563eb;
    font-weight: 600;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.2s ease;
}
.view-all-link:hover {
    color: #1e40af;
    text-decoration: underline;
}
/* END NEW CSS */

/* Removed old .table-card h3 style here as it is replaced by .table-header h3 */

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

/* ===== Progress Bar ===== */
.progress-container {
    margin-top: 8px;
    width: 100%;
    height: 8px;
    background-color: #e5e7eb;
    border-radius: 6px;
    overflow: hidden;
}
.progress-bar {
    height: 100%;
    border-radius: 6px;
    transition: width 0.4s ease-in-out;
}
.progress-bar.Ongoing { background-color: #3b82f6; }
.progress-bar.Pending { background-color: #fbbf24; }
.progress-bar.Completed { background-color: #22c55e; }

.progress-text {
    font-size: 12px;
    color: #6b7280;
    text-align: right;
    margin-top: 4px;
}

/* REMOVED old .view-all block */
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

    <!-- ✅ REPLACED BLOCK START -->
    <div class="table-card">
        <div class="table-header">
            <h3>Recent Projects</h3>
            <a href="uploads/projects.php" class="view-all-link">View All Projects →</a>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Project Name</th>
                    <th>Location</th>
                    <th>Units</th>
                    <th>Status & Progress</th>
                    <th>Start Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects->num_rows > 0): ?>
                    <?php while ($p = $projects->fetch_assoc()): ?>
                        <?php 
                            $progress = intval($p['progress'] ?? 0); 
                            $status = $p['status'];
                            // ⚙️ Optional Enhancement — Auto-Status Handling
                            if ($progress >= 100) {
                                $status = 'Completed';
                            }
                        ?>
                        <tr>
                            <td>
                                <a href="modules/view_project.php?id=<?= $p['id'] ?>"
                                   style="color:#2563eb;text-decoration:none;font-weight:600;">
                                   <?= htmlspecialchars($p['name']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($p['location']) ?></td>
                            <td><?= htmlspecialchars($p['units']) ?></td>
                            <td>
                                <span class="status-badge <?= htmlspecialchars($status) ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                                <div class="progress-container">
                                    <div class="progress-bar <?= htmlspecialchars($status) ?>"
                                         style="width: <?= $progress ?>%;"></div>
                                </div>
                                <div class="progress-text"><?= $progress ?>%</div>
                            </td>
                            <td><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center;color:#6b7280;">No projects found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <!-- ✅ REPLACED BLOCK END -->
</div>

<script>
// Optional: Animate bars on load
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll('.progress-bar').forEach(bar => {
    const width = bar.style.width;
    bar.style.width = '0';
    setTimeout(() => bar.style.width = width, 300);
  });
});
</script>

</div> <!-- closes main-content -->
</body>
</html>