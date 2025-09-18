<?php
// edit_project.php

// --- ALL PHP LOGIC MUST COME BEFORE ANY HTML OUTPUT ---

include 'includes/db.php';
include 'includes/functions.php'; // For is_admin() and session checks

date_default_timezone_set('Asia/Manila');

// --- 1. Security Check ---
// Only admins can edit projects
if (!is_admin()) {
    header("Location: projects.php?status=error&message=" . urlencode("You are not authorized to edit projects."));
    exit();
}

// --- 2. Get Project ID from URL ---
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: projects.php?status=error&message=" . urlencode("No project selected for editing."));
    exit();
}
$project_id = intval($_GET['id']);

// --- 3. Fetch Existing Project Data ---
$stmt_project = $conn->prepare("SELECT p.*, u.username AS constructor_name 
                                FROM projects p 
                                LEFT JOIN users u ON p.constructor_id = u.id 
                                WHERE p.id = ?");
$stmt_project->bind_param("i", $project_id);
$stmt_project->execute();
$project_result = $stmt_project->get_result();
$project_data = $project_result->fetch_assoc();

if (!$project_data) {
    header("Location: projects.php?status=error&message=" . urlencode("Project not found for editing."));
    exit();
}

// --- 4. Handle Form Submission for Update ---
$error_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['project_name']);
    $new_location = trim($_POST['project_location']); // Assuming 'location' column exists
    $new_constructor_id = intval($_POST['constructor_id']);
    $new_status = trim($_POST['status']);

    // Basic validation
    if (empty($new_name) || empty($new_location) || $new_constructor_id <= 0 || empty($new_status)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Prepare and execute the update query
        $update_stmt = $conn->prepare("UPDATE projects SET name = ?, location = ?, constructor_id = ?, status = ? WHERE id = ?");
        $update_stmt->bind_param("ssisi", $new_name, $new_location, $new_constructor_id, $new_status, $project_id);
        
        if ($update_stmt->execute()) {
            header("Location: view_project.php?id=" . $project_id . "&status=project_updated_success");
            exit();
        } else {
            $error_message = "Error updating project: " . $update_stmt->error;
        }
    }
}

// --- START OF HTML OUTPUT ---
include 'includes/header.php';
?>

<div class="main-content-wrapper">
    <div class="edit-project-form-container">
        <div class="edit-project-form-card">
            <div class="form-header">
                <h3>Edit Project: <?= htmlspecialchars($project_data['name']); ?></h3>
                <a href="view_project.php?id=<?= $project_id; ?>" class="close-btn">&times;</a>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <form method="POST" action="edit_project.php?id=<?= $project_id; ?>">
                <div class="form-group">
                    <label for="projectName">Project Name:</label>
                    <input type="text" id="projectName" name="project_name" value="<?= htmlspecialchars($project_data['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="projectLocation">Project Location:</label>
                    <input type="text" id="projectLocation" name="project_location" value="<?= htmlspecialchars($project_data['location']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="constructorSelect">Assign to Constructor:</label>
                    <select id="constructorSelect" name="constructor_id" required>
                        <option value="">-- Select a Constructor --</option>
                        <?php
                        // Fetch constructors (users with role 'constructor')
                        $constructors_stmt = $conn->query("SELECT id, username FROM users WHERE role = 'constructor' ORDER BY username ASC");
                        while ($constructor = $constructors_stmt->fetch_assoc()) {
                            $selected = ($constructor['id'] == $project_data['constructor_id']) ? 'selected' : '';
                            echo '<option value="' . $constructor['id'] . '" ' . $selected . '>' . htmlspecialchars($constructor['username']) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="projectStatus">Project Status:</label>
                    <select id="projectStatus" name="status" required>
                        <?php 
                        $statuses = ['Pending', 'Ongoing', 'Completed'];
                        foreach ($statuses as $status_option) {
                            $selected = ($status_option === $project_data['status']) ? 'selected' : '';
                            echo '<option value="' . $status_option . '" ' . $selected . '>' . $status_option . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <button type="submit" class="update-project-btn">Update Project</button>
            </form>
        </div>
    </div>
</div> <!-- Closes .main-content-wrapper -->

</div> <!-- This closing div likely comes from your header.php file (for .main-container) -->
</body>
</html>

<style>
/* ========================================================= */
/* NEW CSS for edit_project.php                              */
/* Add this to your main stylesheet (e.g., style.css) or    */
/* within the <style> tags in header.php                     */
/* ========================================================= */
.edit-project-form-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 70px - 60px); /* Adjust height to fill remaining viewport, considering header and wrapper padding */
    padding: 20px;
    box-sizing: border-box;
}

.edit-project-form-card {
    background-color: #ffffff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 550px; /* Adjust width as needed */
    position: relative;
}

.edit-project-form-card .form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
}

.edit-project-form-card h3 {
    margin: 0;
    font-size: 1.8em;
    color: #333;
}

.edit-project-form-card .close-btn { /* Used as a back link */
    background: none;
    border: none;
    font-size: 2em; /* Larger for an "X" icon */
    color: #888;
    cursor: pointer;
    text-decoration: none; /* Remove underline for link */
    line-height: 1;
}

.edit-project-form-card .close-btn:hover {
    color: #333;
}

.edit-project-form-card .form-group {
    margin-bottom: 20px;
}

.edit-project-form-card label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #555;
}

.edit-project-form-card input[type="text"],
.edit-project-form-card select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1em;
    box-sizing: border-box;
}

.edit-project-form-card input[type="text"]:focus,
.edit-project-form-card select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    outline: none;
}

.update-project-btn {
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

.update-project-btn:hover {
    background-color: #0056b3;
}

/* Alert messages (reuse from projects.php if possible) */
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
</style>