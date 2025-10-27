<?php
// ======================================
// add_project.php — Combined form + process
// ======================================
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

if (!is_admin()) {
    header("Location: projects.php?status=error&message=" . urlencode("You are not authorized to add projects."));
    exit();
}

date_default_timezone_set('Asia/Manila');

// ===============================
// Handle Form Submission
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name = trim($_POST['project_name']);
    $project_location = trim($_POST['project_location']);
    $units = intval($_POST['units']);
    $status = trim($_POST['status']);

    if (empty($project_name) || empty($project_location) || $units <= 0 || empty($status)) {
        header("Location: add_project.php?status=error&message=" . urlencode("Please fill in all required fields."));
        exit();
    }

    // --- Insert the project ---
    $stmt = $conn->prepare("INSERT INTO projects (name, location, units, status, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssis", $project_name, $project_location, $units, $status);

    if ($stmt->execute()) {
        $project_id = $stmt->insert_id;
        $stmt->close();

        // --- Auto-create the project units (exact count) ---
        $unit_stmt = $conn->prepare("INSERT INTO project_units (project_id, name, description, progress, created_at) VALUES (?, ?, '', 0, NOW())");

        for ($i = 1; $i <= $units; $i++) {
            $unit_name = "Unit " . $i;
            $unit_stmt->bind_param("is", $project_id, $unit_name);
            $unit_stmt->execute();
        }
        $unit_stmt->close();

        // Redirect on success
        header("Location: view_project.php?id={$project_id}&status=success");
        exit();
    } else {
        header("Location: add_project.php?status=error&message=" . urlencode("Error adding project: " . $stmt->error));
        exit();
    }
}

// ===============================
// Display Form
// ===============================
include '../includes/header.php';
?>

<div class="main-content-wrapper">
    <div class="add-project-form-container">
        <div class="add-project-form-card">
            <div class="form-header">
                <h3>Add New Project</h3>
                <a href="projects.php" class="close-btn">&times;</a>
            </div>

            <?php if (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
                <div class="alert error">
                    <?= htmlspecialchars($_GET['message'] ?? 'Error occurred.') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="project_name">Project Name:</label>
                    <input type="text" id="project_name" name="project_name" required>
                </div>

                <div class="form-group">
                    <label for="project_location">Location:</label>
                    <input type="text" id="project_location" name="project_location" required>
                </div>

                <div class="form-group">
                    <label for="units">Number of Units / Houses:</label>
                    <input type="number" id="units" name="units" min="1" required>
                </div>

                <div class="form-group">
                    <label for="status">Project Status:</label>
                    <select id="status" name="status" required>
                        <option value="Pending">Pending</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="save-btn">💾 Save Project</button>
                    <a href="projects.php" class="cancel-btn">✖ Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.add-project-form-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 100px);
    padding: 20px;
}

.add-project-form-card {
    background-color: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 600px;
    animation: fadeIn 0.4s ease;
}

.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
    margin-bottom: 25px;
}

.form-header h3 {
    margin: 0;
}

.close-btn {
    font-size: 1.5em;
    color: #888;
    text-decoration: none;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

input, select {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #ddd;
    font-size: 15px;
}

.save-btn, .cancel-btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: bold;
    text-decoration: none;
    color: #fff;
    cursor: pointer;
    font-size: 15px;
}

.save-btn {
    background-color: #007bff;
    border: none;
}

.save-btn:hover { background-color: #0056b3; }

.cancel-btn {
    background-color: #dc3545;
    margin-left: 10px;
}

.cancel-btn:hover { background-color: #a71d2a; }

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: bold;
}

.alert.error {
    background-color: #f8d7da;
    color: #721c24;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

<?php include '../includes/footer.php'; ?>
