<?php
// ===========================================
// process_add_unit.php
// Handles adding a new house/unit under a project
// ===========================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_csrf();

// --- Get POST data securely ---
$project_id = post_int('project_id');
$name = trim(post_string('name'));
$description = trim(post_string('description', ''));

// --- Validate inputs ---
if (!$project_id || $name === '') {
    die('Invalid data submitted. Please go back and try again.');
}

// --- Insert new house/unit ---
$stmt = $conn->prepare("INSERT INTO project_units (project_id, name, description) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $project_id, $name, $description);

if ($stmt->execute()) {
    // ✅ Success: redirect back to project page
    header("Location: /capstone/view_project.php?id=" . $project_id . "&status=unit_added_success");
    exit();
} else {
    // Database error
    error_log("Error adding unit: " . $stmt->error);
    header("Location: /capstone/view_project.php?id=" . $project_id . "&status=unit_added_error");
    exit();
}
?>
