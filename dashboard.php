<?php
include 'includes/db.php';
include 'includes/header.php'; // This includes your sidebar and opens <div class="main-content">

// --- PHP SEARCH LOGIC (No changes here) ---
$is_search = false;
$search_query = "";
if (isset($_GET['query']) && !empty($_GET['query'])) {
    $is_search = true;
    $search_query = $_GET['query'];
    $search_term = "%" . $search_query . "%";
    $stmt = $conn->prepare("SELECT p.id, p.name, p.created_at, p.status, u.username AS constructor_name FROM projects AS p JOIN users AS u ON p.constructor_id = u.id WHERE p.name LIKE ? OR u.username LIKE ?");
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $projects_result = $stmt->get_result();
} else {
    $query = "SELECT p.id, p.name, p.created_at, p.status, u.username AS constructor_name FROM projects AS p JOIN users AS u ON p.constructor_id = u.id ORDER BY p.created_at DESC LIMIT 3";
    $projects_result = $conn->query($query);
}
?>

<!-- ALL NECESSARY CSS IS HERE TO ENSURE CORRECT LAYOUT -->
<style>
    .main-content {
        padding: 0 !important;
        /* --- THIS IS THE CRUCIAL FIX --- */
        /* It overrides 'align-items: flex-start' from your external stylesheet */
        align-items: stretch !important; 
    }

    /* 1. THE HEADER CONTAINER */
    .main-header {
        position: sticky;
        top: 0;
        z-index: 1000;
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: #ffffff;
        padding: 20px 40px; /* Adjusted padding for a sleeker sticky header */
        border-bottom: 1px solid #e1e8ed;
    }
    .header-left { display: flex; align-items: center; gap: 25px; }
    /* I removed the logo from the header in this version to match your code */
    .header-right { display: flex; align-items: center; gap: 25px; }
    .welcome-message h1 { margin: 0; font-size: 24px; font-weight: 700; color: #2c3e50; }
    .search-bar{display:flex;align-items:center;border:1px solid #ccc;border-radius:24px;padding:5px 8px;width:350px}.search-bar input{border:none;outline:none;width:100%;padding:5px;font-size:14px;background-color:transparent}.search-bar button{background-color:#007bff;border:none;color:white;width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;justify-content:center;align-items:center;font-size:14px}
    .admin-profile{position:relative;cursor:pointer}.admin-avatar{width:40px;height:40px;border-radius:50%;object-fit:cover}.profile-dropdown{top:55px}

    /* 2. THE MAIN CONTENT AREA BELOW THE HEADER */
    .dashboard-content {
        padding: 40px;
        background-color: #f5f6fa;
    }

    /* 3. THE PROJECTS TABLE CONTAINER */
    .projects-section {
        background-color: #ffffff;
        padding: 25px 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        width: 100%;
    }

    /* Table styles */
    .section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.section-title{margin:0;font-size:20px}.view-all-link{text-decoration:none;color:#007bff;font-weight:bold}
    table{width:100%;border-collapse:collapse}th,td{padding:15px;text-align:left;border-bottom:1px solid #f0f0f0}thead{background-color:#f7f7f7;color:#555}th{font-weight:bold;text-transform:uppercase;border-bottom:2px solid #ddd}tbody tr:hover{background-color:#f5f8ff;}.col-project{width:45%}.col-date{width:20%}.col-constructor{width:20%}.col-status{width:15%;text-align:center;}.status-badge{color:white;padding:8px 18px;border-radius:15px;font-size:12px;font-weight:bold;text-transform:capitalize;}.status-badge.ongoing{background-color:#007bff;}.status-badge.completed{background-color:#28a745;}.status-badge.pending{background-color:#ffc107;color:#333;}
</style>

<!-- HEADER (White, Full-Width Container) -->
<header class="main-header">
    <div class="header-left">
        <div class="welcome-message">
            <h1>Welcome, Admin</h1>
        </div>
    </div>
    <div class="header-right">
        <div class="search-container">
            <form action="" method="GET" class="search-bar">
                <input type="text" name="query" placeholder="Search project">
                <button type="submit">🔍</button>
            </form>
        </div>
        <div class="admin-profile" onclick="toggleDropdown()">
            <img src="images/cat.jpg" alt="Admin Avatar" class="admin-avatar">
            <div id="profile-menu" class="profile-dropdown">
                <a href="#profile">My Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</header>

<!-- DASHBOARD CONTENT (Grey, Padded Container that will now fill the space) -->
<div class="dashboard-content">
    <div class="projects-section">
        <div class="section-header">
            <h2 class="section-title">Recent Projects</h2>
            <a href="add_project.php" class="view-all-link">Add New Project →</a>
        </div>
        <table>
            <thead><tr><th class="col-project">PROJECT</th><th class="col-date">DATE</th><th class="col-constructor">CONSTRUCTOR</th><th class="col-status">STATUS</th></tr></thead>
            <tbody>
                <?php if ($projects_result && $projects_result->num_rows > 0): ?>
                    <?php while ($row = $projects_result->fetch_assoc()): ?>
                        <tr>
                            <td class="col-project"><a href="project_materials.php?id=<?= $row['id'] ?>" style="text-decoration:none; color: #0056b3; font-weight:bold;"><?= htmlspecialchars($row['name']) ?></a></td>
                            <td class="col-date"><?= date('F j, Y', strtotime($row['created_at'])) ?></td>
                            <td class="col-constructor"><?= htmlspecialchars($row['constructor_name']) ?></td>
                            <td class="col-status">
                                <?php $status_class = strtolower($row['status']); ?>
                                <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($row['status']) ?></span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding: 20px;">No projects found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div> <!-- This closes main-content div from header.php -->

<!-- JavaScript for dropdown (No changes here) -->
<script>
    function toggleDropdown(){document.getElementById("profile-menu").classList.toggle("show")}window.onclick=function(e){if(!e.target.matches(".admin-profile, .admin-profile *")){for(var n=document.getElementsByClassName("profile-dropdown"),t=0;t<n.length;t++){var o=n[t];o.classList.contains("show")&&o.classList.remove("show")}}}
</script>

</body>
</html>