<?php
include 'includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_FILES['proof_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            if (!is_dir("uploads")) mkdir("uploads", 0775, true);
            $target = 'uploads/' . uniqid('proof_', true) . '.' . $ext;
            move_uploaded_file($_FILES['proof_image']['tmp_name'], $target);
        } else {
            $target = null;
        }
    } else {
        $target = null;
    }

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