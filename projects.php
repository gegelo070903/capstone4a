<?php
// projects.php

include 'includes/db.php';
include 'includes/functions.php';
include 'includes/header.php'; // Assuming this includes the opening <body> tag

// --- PHP Logic for Sorting ---
$sort_order = "DESC"; // Default to newest first
$order_by = "id"; // Default sort column, could also be 'created_at' if you have one

if (isset($_GET['sort'])) {
    if ($_GET['sort'] === 'oldest') {
        $sort_order = "ASC";
        $order_by = "created_at"; // Assuming 'created_at' is a good timestamp for oldest/newest
    } else { // 'newest' or any other value defaults to newest
        $sort_order = "DESC";
        $order_by = "created_at";
    }
}

// Handle search query
$search_query = "";
$search_clause = "";
$search_params = [];
if (isset($_GET['query']) && !empty(trim($_GET['query']))) {
    $search_query = trim($_GET['query']);
    // Search both project name and constructor username
    $search_clause = " WHERE p.name LIKE ? OR u.username LIKE ?";
    $search_params = ["%$search_query%", "%$search_query%"];
}

// Fetch projects from the database based on sort order and search
// UPDATED SELECT STATEMENT: Explicitly selecting 'p.location'
$sql = "SELECT p.*, p.location, u.username as constructor_name 
        FROM projects p 
        LEFT JOIN users u ON p.constructor_id = u.id" . $search_clause .
        " ORDER BY p.{$order_by} {$sort_order}";

$stmt = $conn->prepare($sql);

if (!empty($search_params)) {
    $types = str_repeat('s', count($search_params));
    $stmt->bind_param($types, ...$search_params);
}
$stmt->execute();
$projects = $stmt->get_result();

// --- Handle Status Messages from add_project.php ---
$status_message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $status_message = '<div class="alert success">Project added successfully!</div>';
    } elseif ($_GET['status'] === 'error') {
        $status_message = '<div class="alert error">Error adding project. Please try again.</div>';
    }
}
?>

<!-- ========================================================= -->
<!-- NEW: Search bar for Projects page (will be moved to header by JavaScript) -->
<!-- It's initially hidden and styled to match the header, then made visible after moving. -->
<!-- ========================================================= -->
<div id="projects-search-bar-container" class="header-search-form-container" style="display: none;">
    <form action="projects.php" method="GET" class="header-search-form">
        <input type="text" name="query" placeholder="Search projects..." value="<?php echo htmlspecialchars($search_query); ?>" class="header-search-input">
        <button type="submit" class="header-search-button"><i class="fas fa-search"></i></button>
        <?php 
        // Preserve the current sort order when submitting a search
        if (isset($_GET['sort'])) {
            echo '<input type="hidden" name="sort" value="' . htmlspecialchars($_GET['sort']) . '">';
        }
        ?>
    </form>
</div>
<!-- ========================================================= -->


<div class="main-content-wrapper">
    <div class="header-section">
        <h2 class="section-title">All Projects</h2>
        <div class="actions-and-sort">
            <div class="sort-dropdown">
                <label for="sort-projects">Sort by:</label>
                <select id="sort-projects" onchange="window.location.href = 'projects.php?sort=' + this.value + '<?php echo (!empty($search_query) ? '&query=' . urlencode($search_query) : ''); ?>';">
                    <option value="newest" <?php if (!isset($_GET['sort']) || $_GET['sort'] === 'newest') echo 'selected'; ?>>Newest to Oldest</option>
                    <option value="oldest" <?php if (isset($_GET['sort']) && $_GET['sort'] === 'oldest') echo 'selected'; ?>>Oldest to Newest</option>
                </select>
            </div>
            <?php if (is_admin()): ?>
                <!-- Changed to a button that triggers JavaScript -->
                <button type="button" class="add-new-project-btn" onclick="toggleAddProjectForm()">Add New Project</button>
            <?php endif; ?>
        </div>
    </div>

    <?php echo $status_message; // Display status messages ?>

    <!-- ========================================================= -->
    <!-- NEW: Add New Project Form (initially hidden)              -->
    <!-- ========================================================= -->
    <div id="addProjectFormContainer" class="add-project-form-overlay">
        <div class="add-project-form-card">
            <div class="form-header">
                <h3>Add New Project</h3>
                <button type="button" class="close-btn" onclick="toggleAddProjectForm()">X</button>
            </div>
            <form method="POST" action="add_project.php">
                <div class="form-group">
                    <label for="projectName">Project Name:</label>
                    <input type="text" id="projectName" name="project_name" required>
                </div>
                <!-- NEW: Project Location Input Field -->
                <div class="form-group">
                    <label for="projectLocation">Project Location:</label>
                    <input type="text" id="projectLocation" name="project_location" required>
                </div>
                <div class="form-group">
                    <label for="constructorSelect">Assign to Constructor:</label>
                    <select id="constructorSelect" name="constructor_id" required>
                        <option value="">-- Select a Constructor --</option>
                        <?php
                        // Fetch constructors (users with role 'constructor')
                        $constructors_stmt = $conn->query("SELECT id, username FROM users WHERE role = 'constructor' ORDER BY username ASC");
                        while ($constructor = $constructors_stmt->fetch_assoc()) {
                            echo '<option value="' . $constructor['id'] . '">' . htmlspecialchars($constructor['username']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="create-project-btn">Create Project</button>
            </form>
        </div>
    </div>
    <!-- ========================================================= -->

    <div class="project-grid">
        <?php if ($projects->num_rows > 0): ?>
            <?php while ($project = $projects->fetch_assoc()): ?>
                <a href="view_project.php?id=<?php echo $project['id']; ?>" class="project-card">
                    <div class="project-icon">
                        <i class="fas fa-folder"></i>
                    </div>
                    <div class="project-status <?php echo strtolower($project['status']); ?>">
                        <?php echo htmlspecialchars($project['status']); ?>
                    </div>
                    <h3 class="project-name"><?php echo htmlspecialchars($project['name']); ?></h3>
                    <!-- NEW: Display Project Location -->
                    <p class="project-location">Location: <?php echo htmlspecialchars($project['location']); ?></p>
                    <p class="project-constructor">Constructor: <?php echo htmlspecialchars($project['constructor_name']); ?></p>
                </a>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-projects">No projects found.</p>
        <?php endif; ?>
    </div>
</div>

</div> <!-- This closing div likely comes from your header.php file -->

<script>
// Function to toggle the Add Project Form overlay
function toggleAddProjectForm() {
    const formContainer = document.getElementById('addProjectFormContainer');
    if (formContainer.style.display === 'flex') {
        formContainer.style.display = 'none';
    } else {
        formContainer.style.display = 'flex';
    }
}

// JavaScript to move the search bar to the header placeholder
document.addEventListener('DOMContentLoaded', function() {
    const projectsSearchBar = document.getElementById('projects-search-bar-container');
    // This ID should match the placeholder div in your includes/header.php
    const headerPlaceholder = document.getElementById('dynamic-search-placeholder'); 

    if (projectsSearchBar && headerPlaceholder) {
        headerPlaceholder.appendChild(projectsSearchBar);
        projectsSearchBar.style.display = 'block'; // Make it visible after moving
    }

    // Ensure the sort dropdown reflects the current URL parameter on load
    const sortSelect = document.getElementById('sort-projects');
    if (sortSelect) {
        const urlParams = new URLSearchParams(window.location.search);
        const currentSort = urlParams.get('sort') || 'newest'; // Default to 'newest'
        sortSelect.value = currentSort;
    }
});
</script>
</body>
</html>

<!-- ========================================================= -->
<!-- NEW: CSS for the Add Project Form and other elements      -->
<!-- Add this to your main stylesheet (e.g., style.css) or    -->
<!-- within the <style> tags in header.php or projects.php     -->
<!-- ========================================================= -->
<style>
/* --- Existing CSS for .main-content-wrapper, .header-section, etc. --- */
/* (Keep these as they are, from your original file) */

.main-content-wrapper {
    padding: 30px;
    max-width: 1200px;
    margin: 0 auto;
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
}

.header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.section-title {
    font-size: 2em;
    color: #333;
    margin: 0;
}

.actions-and-sort {
    display: flex;
    align-items: center;
    gap: 20px;
}

.sort-dropdown label {
    margin-right: 10px;
    font-weight: 500;
    color: #555;
}

.sort-dropdown select {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 1em;
    cursor: pointer;
    background-color: #fff;
    min-width: 120px;
}

.add-new-project-btn {
    background-color: #007bff;
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    transition: background-color 0.3s ease;
    white-space: nowrap;
    border: none; /* Make it look like a button */
    cursor: pointer; /* Indicate it's clickable */
}

.add-new-project-btn:hover {
    background-color: #0056b3;
}

.project-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 25px;
}

.project-card {
    background-color: #ffffff;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    padding: 25px;
    text-align: left;
    text-decoration: none;
    color: #333;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
}

.project-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.project-icon {
    font-size: 3em;
    color: #007bff;
    margin-bottom: 15px;
    text-align: right;
}

.project-icon i {
    float: left;
    margin-right: 15px;
}

.project-status {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: bold;
    color: white;
    text-transform: uppercase;
}

.project-status.pending { background-color: #ffc107; }
.project-status.ongoing { background-color: #007bff; }
.project-status.completed { background-color: #28a745; }
.project-status.cancelled { background-color: #dc3545; }


.project-name {
    font-size: 1.5em;
    margin-top: 10px;
    margin-bottom: 8px;
    color: #333;
}

/* NEW CSS for Project Location */
.project-location {
    font-size: 0.95em;
    color: #6c757d;
    margin-top: 5px;
    margin-bottom: 5px;
}

.project-constructor {
    font-size: 0.9em;
    color: #666;
    margin-bottom: 0;
}

.no-projects {
    grid-column: 1 / -1;
    text-align: center;
    color: #777;
    font-style: italic;
    padding: 30px;
}

/* --- CSS FOR THE ADD PROJECT FORM OVERLAY --- */
.add-project-form-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1002;
}

.add-project-form-card {
    background-color: #ffffff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    width: 100%;
    max-width: 500px;
    position: relative;
}

.add-project-form-card .form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
}

.add-project-form-card h3 {
    margin: 0;
    font-size: 1.8em;
    color: #333;
}

.add-project-form-card .close-btn {
    background: none;
    border: none;
    font-size: 1.5em;
    color: #888;
    cursor: pointer;
    transition: color 0.2s ease;
}

.add-project-form-card .close-btn:hover {
    color: #333;
}

.add-project-form-card .form-group {
    margin-bottom: 20px;
}

.add-project-form-card label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #555;
}

.add-project-form-card input[type="text"],
.add-project-form-card select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1em;
    box-sizing: border-box;
}

.add-project-form-card input[type="text"]:focus,
.add-project-form-card select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    outline: none;
}

.create-project-btn {
    background-color: #007bff;
    color: white;
    padding: 12px 25px;
    border-radius: 8px;
    border: none;
    font-size: 1.1em;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.3s ease;
    width: 100%;
    margin-top: 15px;
}

.create-project-btn:hover {
    background-color: #0056b3;
}

/* Alert messages */
.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: bold;
    text-align: center;
}

.alert.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* --- Suggested CSS for the Header Search Bar --- */
/* These styles should ideally be in your main stylesheet or header.php's styling */
/* Ensure the `dynamic-search-placeholder` in `header.php` is styled to accommodate this. */
.header-search-form-container {
    /* Styles for the container when it's in the header */
    display: flex; /* Will be 'block' or 'flex' based on the placeholder's display property */
    align-items: center;
    flex-grow: 1; /* Allows the search bar to take available space */
    max-width: 400px; /* Adjust as needed for your header layout */
    margin-right: 20px; /* Spacing from profile icon/other header elements */
}

.header-search-form {
    display: flex;
    width: 100%;
    border: 1px solid #ddd;
    border-radius: 20px;
    overflow: hidden;
    background-color: #f9f9f9;
}

.header-search-input {
    border: none;
    padding: 8px 15px;
    flex-grow: 1;
    font-size: 0.95em;
    outline: none;
    background-color: transparent;
    min-width: 0; /* Allows shrinking in flex container */
}

.header-search-button {
    background-color: #e9ecef;
    border: none;
    padding: 8px 15px;
    cursor: pointer;
    color: #555;
    transition: background-color 0.2s ease;
}

.header-search-button:hover {
    background-color: #dee2e6;
}
</style>