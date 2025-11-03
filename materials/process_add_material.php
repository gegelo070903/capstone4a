<?php
// materials/process_add_material.php

// Ensure PHP error reporting is off for production, but kept here for development context
// ini_set('display_errors', 0);
// ini_set('display_startup_errors', 0);
// error_reporting(0);

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

// Set timezone for consistent NOW() in SQL and date/time functions
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Prevent direct access via GET
    header('Location: ../uploads/projects.php');
    exit;
}

// ✅ 1. Collect and sanitize inputs
$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$name = trim($_POST['name'] ?? '');
$supplier = trim($_POST['supplier'] ?? '');
// NOTE: Use 'quantity' from the form submission
$total_quantity = (float)($_POST['quantity'] ?? 0); 
$unit = trim($_POST['unit_of_measurement'] ?? '');
$purpose = trim($_POST['purpose'] ?? '');

// ✅ 2. Validate inputs
if ($project_id <= 0 || $name === '' || $total_quantity <= 0 || $unit === '') {
    // Redirect with the original error style if validation fails
    echo "<p style='color:red; font-family:Arial; margin:20px;'>Error: Missing or invalid required fields. Please ensure Project ID is valid, Name is filled, Quantity is greater than 0, and Unit is filled.</p>";
    exit;
}

$error_message = '';
$success = false;

// === START MERGE/UPSERT LOGIC ===
// Using a transaction for safety during the check and update/insert
$conn->begin_transaction();

try {
    // ✅ 3. Check if material already exists for the same project
    $check = $conn->prepare("SELECT id, total_quantity, remaining_quantity FROM materials WHERE name = ? AND project_id = ?");
    if (!$check) throw new Exception("Database prepare error (Check): " . $conn->error);
    
    $check->bind_param('si', $name, $project_id);
    $check->execute();
    $result = $check->get_result();
    $check->close();

    if ($result->num_rows > 0) {
        // ✅ 4. Material exists → Update totals
        $row = $result->fetch_assoc();
        $new_total = $row['total_quantity'] + $total_quantity;
        $new_remaining = $row['remaining_quantity'] + $total_quantity;
        $material_id = $row['id'];

        $update = $conn->prepare("
            UPDATE materials 
            SET total_quantity = ?, remaining_quantity = ?, supplier = ?, purpose = ?, unit_of_measurement = ?, created_at = NOW()
            WHERE id = ?
        ");
        if (!$update) throw new Exception("Database prepare error (Update): " . $conn->error);
        
        $update->bind_param('ddsssi', $new_total, $new_remaining, $supplier, $purpose, $unit, $material_id);
        if (!$update->execute()) throw new Exception("Error updating material: " . $update->error);
        $update->close();
        $success = true;

    } else {
        // ✅ 5. Insert new material if not found
        $remaining_quantity = $total_quantity; 
        
        $stmt = $conn->prepare("
            INSERT INTO materials 
            (project_id, name, supplier, total_quantity, remaining_quantity, unit_of_measurement, purpose, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) throw new Exception("Database prepare error (Insert): " . $conn->error);
        
        $stmt->bind_param('issddss', $project_id, $name, $supplier, $total_quantity, $remaining_quantity, $unit, $purpose);
        if (!$stmt->execute()) throw new Exception("Error adding new material: " . $stmt->error);
        $stmt->close();
        $success = true;
    }
    
    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    $error_message = $e->getMessage();
}

// ✅ 6. Redirect or display error
if ($success) {
    // Redirect to view_project.php with tab=materials
    header("Location: ../modules/view_project.php?id=$project_id&tab=materials");
    exit;
} else {
    // Display error message
    echo "<p style='color:red; font-family:Arial; margin:20px;'>Error processing material: " . htmlspecialchars($error_message) . "</p>";
    exit;
}
?>