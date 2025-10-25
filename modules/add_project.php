<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
?>

<div class="overlay-card">
  <button class="close-btn" onclick="toggleOverlay(false)">&times;</button>
  <h3>Add New Project</h3>
  <form action="../modules/process_add_project.php" method="POST">
    
    <label>Project Name</label>
    <input type="text" name="project_name" required>

    <label>Location</label>
    <input type="text" name="location" required>

    <label>Start Date</label>
    <input type="date" name="start_date" required>

    <label>Number of Units / Houses</label>
    <input type="number" name="units" min="1" required>

    <div class="overlay-actions">
      <button type="button" class="btn-cancel" onclick="toggleOverlay(false)">Cancel</button>
      <button type="submit" class="btn-primary">Save Project</button>
    </div>
  </form>
</div>
