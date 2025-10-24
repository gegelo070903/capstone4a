<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

// Ensure only admin can use this script
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: projects.php?status=unauthorized');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $project_name = trim($_POST['project_name']);
    $location = trim($_POST['location']);
    $start_date = $_POST['start_date'];
    $constructor_id = intval($_POST['constructor_id']);
    $status = 'Pending';

    // Basic validation
    if (empty($project_name) || empty($location) || empty($start_date) || $constructor_id <= 0) {
        header("Location: projects.php?status=error");
        exit();
    }

    // ✅ Include location in the INSERT query
    $stmt = $conn->prepare("INSERT INTO projects (name, location, created_at, constructor_id, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $project_name, $location, $start_date, $constructor_id, $status);

    if ($stmt->execute()) {
        $project_id = $conn->insert_id;
        $safe_name = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $project_name));
        $folder_name = $project_id . '_' . $safe_name;
        $folder_path = 'uploads/projects/' . $folder_name;

        // Create folder if not exists
        if (!is_dir($folder_path)) {
            mkdir($folder_path, 0755, true);
        }

        // Save folder path
        $update_stmt = $conn->prepare("UPDATE projects SET folder_path = ? WHERE id = ?");
        $update_stmt->bind_param("si", $folder_path, $project_id);
        $update_stmt->execute();

        header("Location: projects.php?status=success");
        exit();
    } else {
        error_log("Error adding project: " . $stmt->error);
        header("Location: projects.php?status=error");
        exit();
    }
} else {
    header("Location: projects.php");
    exit();
}
?>
