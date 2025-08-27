<?php
session_start(); 
include 'includes/db.php';
include 'includes/header.php'; // Includes sidebar and top header

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: login.php');
    exit();
}

// ======================================================================
// THIS IS THE COMPLETE PHP LOGIC THAT WAS MISSING BEFORE
// ======================================================================
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$page_title = "";
$is_search = isset($_GET['query']) && !empty(trim($_GET['query']));

if ($is_search) {
    $search_query = trim($_GET['query']);
    $page_title = "Search Results for '" . htmlspecialchars($search_query) . "'";
    $search_term = "%" . $search_query . "%";

    $base_query = "SELECT p.id, p.name, p.created_at, p.status, u.username AS constructor_name FROM projects p JOIN users u ON p.constructor_id = u.id WHERE (p.name LIKE ? OR u.username LIKE ?)";

    if ($user_role === 'admin') {
        $stmt = $conn->prepare($base_query . " ORDER BY p.created_at DESC");
        $stmt->bind_param("ss", $search_term, $search_term);
    } else {
        $final_query = $base_query . " AND p.constructor_id = ?";
        $stmt = $conn->prepare($final_query);
        $stmt->bind_param("ssi", $search_term, $search_term, $user_id);
    }
    $stmt->execute();
    $projects_result = $stmt->get_result();

} else {
    // Original logic for when there is NO search
    $base_query = "SELECT p.id, p.name, p.created_at, p.status, u.username AS constructor_name FROM projects p JOIN users u ON p.constructor_id = u.id";

    if ($user_role === 'admin') {
        $page_title = "All Projects";
        $projects_result = $conn->query($base_query . " ORDER BY p.created_at DESC");
    } else {
        $page_title = "My Assigned Projects";
        $stmt = $conn->prepare($base_query . " WHERE p.constructor_id = ? ORDER BY p.created_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $projects_result = $stmt->get_result();
    }
}

// Fetch constructors for the "Add Project" modal (admin only)
$constructors_result = null;
if ($user_role === 'admin') {
    $constructors_result = $conn->query("SELECT id, username FROM users WHERE role = 'constructor' ORDER BY username");
}
?>

<!-- CSS for the Folder-Based Project Design -->
<style>
/* Page Header (Title & Button) */
.projects-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.projects-title { font-size: 28px; font-weight: 700; }
.btn-primary { background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; text-decoration: none; transition: background-color 0.2s; }
.btn-primary:hover { background-color: #0056b3; }

/* Grid Container for the Project Folders */
.projects-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; }

/* Individual Project Folder Styling */
.project-folder { background-color: #F0EFEA; border-radius: 0 8px 8px 8px; padding: 20px; text-decoration: none; color: inherit; position: relative; border: 1px solid #DCDCDC; transition: transform 0.2s ease, box-shadow 0.2s ease; }
.project-folder:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
.project-folder::before { content: ''; position: absolute; top: -10px; left: 0; width: 80px; height: 10px; background-color: #F0EFEA; border-top: 1px solid #DCDCDC; border-left: 1px solid #DCDCDC; border-right: 1px solid #DCDCDC; border-radius: 8px 8px 0 0; }
.folder-icon { font-size: 2.5rem; color: #007bff; margin-bottom: 15px; }
.folder-name { font-size: 18px; font-weight: 700; color: #2c3e50; margin: 0; }
.folder-info { font-size: 14px; color: #555; margin-top: 10px; }
.folder-info p { margin: 4px 0; }
.status-badge { float: right; color: #ffffff; padding: 5px 15px; border-radius: 15px; font-size: 12px; font-weight: bold; text-transform: capitalize; }
.status-badge.ongoing { background-color: #007bff; }
.status-badge.completed { background-color: #28a745; }
.status-badge.pending { background-color: #ffc107; color: #333; }
.empty-state { background-color: #fff; padding: 40px; text-align: center; border-radius: 12px; color: #555; }

/* Modal styles */
.modal{display:none;position:fixed;z-index:1050;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.5)}.modal-content{background-color:#fefefe;margin:10% auto;padding:30px;border:1px solid #888;width:80%;max-width:500px;border-radius:12px;position:relative}.close-button{color:#aaa;float:right;font-size:28px;font-weight:bold;position:absolute;top:10px;right:20px;cursor:pointer}.form-group{margin-bottom:20px}.form-group label{display:block;margin-bottom:8px;font-weight:bold}.form-group input,.form-group select{width:100%;padding:12px;border:1px solid #ccc;border-radius:6px;box-sizing:border-box}
</style>

<!-- Add Font Awesome for the folder icon -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<div class="projects-header">
    <h1 class="projects-title"><?= $page_title ?></h1>
    <?php if ($user_role === 'admin' && !$is_search): ?>
        <button id="addProjectBtn" class="btn-primary">Add New Project</button>
    <?php endif; ?>
</div>

<div class="projects-grid" id="projects-container">
    <?php if ($projects_result && $projects_result->num_rows > 0): ?>
        <?php while($row = $projects_result->fetch_assoc()): ?>
            <a href="view_project.php?id=<?= $row['id'] ?>" class="project-folder">
                <?php $status_class = strtolower($row['status']); ?>
                <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($row['status']) ?></span>
                <div class="folder-icon"><i class="fas fa-folder"></i></div>
                <h3 class="folder-name"><?= htmlspecialchars($row['name']) ?></h3>
                <div class="folder-info">
                    <p><strong>Constructor:</strong> <?= htmlspecialchars($row['constructor_name']) ?></p>
                </div>
            </a>
        <?php endwhile; ?>
    <?php else: ?>
        </div> <!-- Close grid early -->
        <div class="empty-state">
            <h4><?= $is_search ? 'No projects found matching your search.' : 'No projects have been created yet.' ?></h4>
        </div>
    <?php endif; ?>

<?php if ($projects_result && $projects_result->num_rows > 0): ?>
    </div> <!-- This closes the .projects-grid if there were results -->
<?php endif; ?>


<!-- Add Project Modal and its JavaScript -->
<?php if ($user_role === 'admin'): ?>
<div id="addProjectModal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2>Create a New Project</h2>
        <form action="process_add_project.php" method="POST">
            <div class="form-group">
                <label for="name">Project Name</label>
                <input type="text" id="name" name="project_name" required>
            </div>
            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" required>
            </div>
            <div class="form-group">
                <label for="constructor">Assign Constructor</label>
                <select id="constructor" name="constructor_id" required>
                    <option value="">-- Select a Constructor --</option>
                    <?php if($constructors_result) { $constructors_result->data_seek(0); while($constructor = $constructors_result->fetch_assoc()){ echo "<option value='{$constructor['id']}'>".htmlspecialchars($constructor['username'])."</option>"; } } ?>
                </select>
            </div>
            <button type="submit" class="btn-primary">Create Project</button>
        </form>
    </div>
</div>

<script>
var modal = document.getElementById("addProjectModal");
var btn = document.getElementById("addProjectBtn");
var span = document.getElementsByClassName("close-button")[0];
if(btn) { btn.onclick = function() { modal.style.display = "block"; } }
if(span) { span.onclick = function() { modal.style.display = "none"; } }
window.onclick = function(event) { if (event.target == modal) { modal.style.display = "none"; } }
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>