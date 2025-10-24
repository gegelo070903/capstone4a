<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
if (!is_admin()) {
    header('Location: projects.php');
    exit();
}

require_once __DIR__ . '/includes/header.php';

// Fetch all constructors (users)
$constructors = $conn->query("SELECT id, username FROM users WHERE role = 'constructor' ORDER BY username ASC");
?>

<style>
:root {
  --sidebar-width: 250px;
  --gap: 30px;
  --brand-blue: #2563eb;
}

body {
  background-color: #f3f4f6;
  font-family: 'Inter', 'Segoe UI', sans-serif;
  color: #1f2937;
}

.main-container {
  margin-left: var(--sidebar-width);
  padding: var(--gap);
  min-height: 100vh;
  box-sizing: border-box;
  display: flex;
  justify-content: center;
  align-items: flex-start;
}

.form-container {
  background: #fff;
  border-radius: 12px;
  padding: 40px 50px;
  max-width: 650px;
  width: 100%;
  box-shadow: 0 8px 20px rgba(0,0,0,0.08);
  border: 1px solid #e5e7eb;
}

h2 {
  font-size: 28px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 25px;
  text-align: center;
}

label {
  display: block;
  font-weight: 600;
  margin-bottom: 6px;
  color: #374151;
}

input[type="text"], input[type="date"], select {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 15px;
  color: #1f2937;
  margin-bottom: 18px;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
input:focus, select:focus {
  border-color: var(--brand-blue);
  box-shadow: 0 0 0 3px rgba(37,99,235,0.2);
  outline: none;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
}

button {
  background: var(--brand-blue);
  color: white;
  border: none;
  border-radius: 6px;
  padding: 10px 20px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease, box-shadow 0.2s ease;
}
button:hover {
  background: #1d4ed8;
  box-shadow: 0 3px 6px rgba(37,99,235,0.3);
}

.cancel-btn {
  background: #6b7280;
}
.cancel-btn:hover {
  background: #4b5563;
}
</style>

<div class="main-container">
  <div class="form-container">
    <h2>Add New Project</h2>
    <form action="process_add_project.php" method="POST">
      <label for="project_name">Project Name</label>
      <input type="text" name="project_name" id="project_name" placeholder="Enter project name" required>

      <label for="start_date">Start Date</label>
      <input type="date" name="start_date" id="start_date" required>

      <label for="constructor_id">Constructor</label>
      <select name="constructor_id" id="constructor_id" required>
        <option value="">-- Select Constructor --</option>
        <?php while ($c = $constructors->fetch_assoc()): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['username']) ?></option>
        <?php endwhile; ?>
      </select>

      <div class="form-actions">
        <a href="projects.php" class="cancel-btn" style="text-decoration:none; color:white; padding:10px 20px; border-radius:6px;">Cancel</a>
        <button type="submit">Save Project</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
