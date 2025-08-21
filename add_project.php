<?php
include 'includes/db.php';
include 'includes/header.php';

// Fetch all users who are constructors to populate the dropdown
$constructors_result = $conn->query("SELECT id, username FROM users WHERE role = 'constructor'");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name = $_POST['project_name'];
    $constructor_id = $_POST['constructor_id'];

    // Basic validation
    if (!empty($project_name) && !empty($constructor_id)) {
        $stmt = $conn->prepare("INSERT INTO projects (name, constructor_id, status) VALUES (?, ?, 'Ongoing')");
        $stmt->bind_param("si", $project_name, $constructor_id);
        
        if ($stmt->execute()) {
            echo "<p style='color:green;'>Project added successfully!</p>";
        } else {
            echo "<p style='color:red;'>Error adding project.</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color:red;'>Please fill in all fields.</p>";
    }
}
?>

<style>
    .form-container { max-width: 600px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
    .form-group button { background: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
</style>

<div class="form-container">
    <h2>Add New Project</h2>
    <form action="add_project.php" method="POST">
        <div class="form-group">
            <label for="project_name">Project Name</label>
            <input type="text" id="project_name" name="project_name" required>
        </div>
        <div class="form-group">
            <label for="constructor_id">Assign to Constructor</label>
            <select id="constructor_id" name="constructor_id" required>
                <option value="">-- Select a Constructor --</option>
                <?php while($constructor = $constructors_result->fetch_assoc()): ?>
                    <option value="<?= $constructor['id'] ?>"><?= htmlspecialchars($constructor['username']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <button type="submit">Create Project</button>
        </div>
    </form>
</div>

</body>
</html>