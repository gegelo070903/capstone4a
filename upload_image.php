<?php
include 'includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle file upload
    if ($_FILES['proof_image']['error'] == 0) {
        $target = 'uploads/' . basename($_FILES['proof_image']['name']);
        move_uploaded_file($_FILES['proof_image']['tmp_name'], $target);
    } else {
        $target = null;
    }

    // Save to database
    $stmt = $conn->prepare("INSERT INTO construction_reports (constructor_id, report_date, start_time, end_time, status, description, proof_image, materials_left) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssssss',
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
    header('Location: development_monitoring.php');
}
?>