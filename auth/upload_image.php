<?php
// Upload Image Script (for Proof of Work)
// Handles file upload and record insertion for construction reports
// Include database connection from /includes
include '../includes/db.php';

// Start session to access logged-in user data
session_start();
// Handle POST request (image upload + report insertion)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $target = null; // Default to null if no valid file uploaded

    // Check if an image file is uploaded without errors
    if ($_FILES['proof_image']['error'] == 0) {

        // Get file extension and validate allowed types
        $ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {

            // Ensure uploads directory exists in the root folder
            if (!is_dir("../uploads")) {
                mkdir("../uploads", 0775, true);
            }

            // Build target file path for upload
            $target = '../uploads/' . uniqid('proof_', true) . '.' . $ext;

            // Move uploaded file to uploads directory
            move_uploaded_file($_FILES['proof_image']['tmp_name'], $target);

        } else {
            $target = null;
        }
    }
    // Insert report record into database
    $stmt = $conn->prepare("INSERT INTO construction_reports (constructor_id, report_date, start_time, end_time, status, description, proof_image, materials_left) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        'isssssss',
        $_POST['user_id'],
        $_POST['report_date'],
        $_POST['start_time'],
        $_POST['end_time'],
        $_POST['status'],
        $_POST['description'],
        $target,
        $_POST['materials_left']
    );
    $stmt->execute();

    // Redirect to Development Monitoring page
    header('Location: ../development/development_monitoring.php');
    exit();
}
?>
