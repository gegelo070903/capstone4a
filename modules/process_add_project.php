<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name = trim($_POST['project_name']);
    $location = trim($_POST['location']);
    $start_date = $_POST['start_date'];
    $units = intval($_POST['units']);
    $status = 'Pending';

    // Create folder for project files
    $folder_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($project_name));
    $folder_path = "../uploads/projects/" . $folder_name;

    if (!is_dir($folder_path)) {
        mkdir($folder_path, 0777, true);
    }

    // Insert project data
    $stmt = $conn->prepare("INSERT INTO projects (name, location, units, status, created_at, folder_path) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisss", $project_name, $location, $units, $status, $start_date, $folder_path);

    if ($stmt->execute()) {
        header("Location: ../uploads/projects.php?success=1");
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
