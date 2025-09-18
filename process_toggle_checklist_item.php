<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php'; // For is_admin()

header('Content-Type: application/json'); // Respond with JSON

$response = ['success' => false, 'message' => ''];

// Ensure only admins can toggle checklist items
if (!is_admin()) {
    $response['message'] = "Unauthorized access.";
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $checklist_id = intval($_POST['checklist_id']);
    $is_completed = intval($_POST['is_completed']); // 1 or 0
    $project_id_for_redirect = intval($_POST['project_id']); // Used for redirection after AJAX success

    if (empty($checklist_id)) {
        $response['message'] = "Invalid checklist ID.";
        echo json_encode($response);
        exit();
    }

    $completed_by_user_id = null;
    $completed_at = null;

    if ($is_completed) {
        $completed_by_user_id = $_SESSION['user_id']; // Log the user who completed it
        $completed_at = date('Y-m-d H:i:s'); // Log the completion time
        $stmt = $conn->prepare("UPDATE project_checklists SET is_completed = 1, completed_by_user_id = ?, completed_at = ? WHERE id = ? AND project_id = ?");
        $stmt->bind_param("isii", $completed_by_user_id, $completed_at, $checklist_id, $project_id_for_redirect);
    } else {
        // If unchecking, clear completed_by and completed_at
        $stmt = $conn->prepare("UPDATE project_checklists SET is_completed = 0, completed_by_user_id = NULL, completed_at = NULL WHERE id = ? AND project_id = ?");
        $stmt->bind_param("ii", $checklist_id, $project_id_for_redirect);
    }

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Checklist item updated successfully.";
    } else {
        $response['message'] = "Database error: " . $conn->error;
    }
} else {
    $response['message'] = "Invalid request method.";
}

echo json_encode($response);
exit();
?>