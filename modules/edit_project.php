<?php 
// edit_project.php (Final version for /modules/ folder)

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

date_default_timezone_set('Asia/Manila');

// --- 1. Security Check ---
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
$stmt_project = $conn->prepare("SELECT * FROM projects WHERE id = ?");
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
    $new_location = trim($_POST['project_location']);
    $new_units = intval($_POST['units']);
    $new_status = trim($_POST['status']);

    // Basic validation
    if (empty($new_name) || empty($new_location) || $new_units <= 0 || empty($new_status)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Update query (constructor_id removed)
        $update_stmt = $conn->prepare("UPDATE projects SET name = ?, location = ?, units = ?, status = ? WHERE id = ?");
        $update_stmt->bind_param("ssisi", $new_name, $new_location, $new_units, $new_status, $project_id);
        
        if ($update_stmt->execute()) {
            header("Location: view_project.php?id=" . $project_id . "&status=project_updated_success");
            exit();
        } else {
            $error_message = "Error updating project: " . $update_stmt->error;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
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
                    <label for="projectUnits">Number of Units / Houses:</label>
                    <input type="number" id="projectUnits" name="units" value="<?= htmlspecialchars($project_data['units']); ?>" min="1" required>
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
</div>

</div> <!-- close main-container -->
</body>
</html>

<style>
.edit-project-form-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 70px - 60px);
    padding: 20px;
    box-sizing: border-box;
}

.edit-project-form-card {
    background-color: #ffffff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 550px;
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

.edit-project-form-card .close-btn {
    background: none;
    border: none;
    font-size: 2em;
    color: #888;
    cursor: pointer;
    text-decoration: none;
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
.edit-project-form-card input[type="number"],
.edit-project-form-card select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1em;
    box-sizing: border-box;
}

.edit-project-form-card input[type="text"]:focus,
.edit-project-form-card input[type="number"]:focus,
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
