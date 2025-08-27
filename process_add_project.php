<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Access denied.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $project_name = trim($_POST['project_name']);
    $start_date = $_POST['start_date'];
    $constructor_id = $_POST['constructor_id'];
    $status = 'Pending';

    if (empty($project_name) || empty($start_date) || empty($constructor_id)) {
        die("Error: All fields are required.");
    }

    $stmt = $conn->prepare("INSERT INTO projects (name, created_at, constructor_id, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $project_name, $start_date, $constructor_id, $status);

    if ($stmt->execute()) {
        $project_id = $conn->insert_id;
        $safe_name = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $project_name));
        $folder_name = $project_id . '_' . $safe_name;
        $folder_path = 'uploads/projects/' . $folder_name;

        if (!is_dir($folder_path)) {
            mkdir($folder_path, 0755, true); 
        }

        $update_stmt = $conn->prepare("UPDATE projects SET folder_path = ? WHERE id = ?");
        $update_stmt->bind_param("si", $folder_path, $project_id);
        $update_stmt->execute();
        
        header("Location: projects.php?success=1");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>