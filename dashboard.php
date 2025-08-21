<?php
// main dashboard
include 'includes/db.php';
include 'includes/header.php';
?>

<h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>
<p>This is your dashboard. Use the navigation to access different modules.</p>

<!-- Recent Projects Section -->
<div class="projects-section">
    <div class="section-header">
        <h2 class="section-title">Recent Projects</h2>
        <a href="projects.php" class="view-all-link">View all Project →</a>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>PROJECT</th>
                <th>DATE</th>
                <th>CONSTRUCTOR</th>
                <th>RESULT</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="project-name">Camella Homes</div>
                </td>
                <td>
                    <div class="project-date">Today</div>
                </td>
                <td>
                    <div class="project-constructor">Eng. Cardo Dalisay</div>
                </td>
                <td>
                    <span class="status-badge">Ongoing</span>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="project-name">Lumina Homes</div>
                </td>
                <td>
                    <div class="project-date">Today</div>
                </td>
                <td>
                    <div class="project-constructor">Eng. Juan Dela Cruz</div>
                </td>
                <td>
                    <span class="status-badge">Ongoing</span>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="project-name">W Torch Dev</div>
                </td>
                <td>
                    <div class="project-date">Yesterday</div>
                </td>
                <td>
                    <div class="project-constructor">Eng. John Doe</div>
                </td>
                <td>
                    <span class="status-badge">Ongoing</span>
                </td>
            </tr>
        </tbody>
    </table>
</div>

</div>
</body>
</html>