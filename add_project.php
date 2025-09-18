<?php
// add_project.php - Handles project creation logic

include 'includes/db.php';
include 'includes/functions.php'; // For is_admin() check

// Ensure only admins can access this script
if (!is_admin()) {
    header('Location: projects.php'); // Redirect non-admins
    exit();
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name = trim($_POST['project_name']);
    $constructor_id = intval($_POST['constructor_id']);
    // Add other fields if you have them in the form (e.g., location, start_date, status)
    
    // Basic validation
    if (empty($project_name) || $constructor_id <= 0) {
        // Redirect back with an error status
        header('Location: projects.php?status=error');
        exit();
    }

    // Default status for a new project
    $status = 'Pending'; // Or 'Ongoing', adjust as per your workflow

    // Prepare and execute the database insertion
    // Make sure your 'projects' table has columns for 'name', 'constructor_id', 'status'
    $stmt = $conn->prepare("INSERT INTO projects (name, constructor_id, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $project_name, $constructor_id, $status); // 's' for name, 'i' for ID, 's' for status

    if ($stmt->execute()) {
        // Redirect back to projects.php with a success status
        header('Location: projects.php?status=success');
        exit();
    } else {
        // Log the error for debugging (optional)
        error_log("Error adding project: " . $stmt->error);
        // Redirect back with an error status
        header('Location: projects.php?status=error');
        exit();
    }
} else {
    // If accessed directly without POST, redirect to projects page
    header('Location: projects.php');
    exit();
}
?>