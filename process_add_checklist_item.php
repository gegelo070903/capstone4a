<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php'; // For is_admin()

if (!is_admin()) {
    header("Location: login.php"); // Only admin can add checklist items
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_id = intval($_POST['project_id']);
    $item_description = trim($_POST['item_description']);

    // Ensure user_id is set in session for logging who completed the task
    $completed_by_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    if (empty($project_id) || empty($item_description)) {
        header("Location: view_project.php?id=" . $project_id . "&status=checklist_item_added_error&message=Description cannot be empty.");
        exit();
    }

    // Insert the new checklist item
    $stmt = $conn->prepare("INSERT INTO project_checklists (project_id, item_description, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $project_id, $item_description);

    if ($stmt->execute()) {
        header("Location: view_project.php?id=" . $project_id . "&status=checklist_item_added_success");
        exit();
    } else {
        header("Location: view_project.php?id=" . $project_id . "&status=checklist_item_added_error&message=" . urlencode($conn->error));
        exit();
    }
} else {
    header("Location: dashboard.php"); // Redirect if not a POST request
    exit();
}
?>