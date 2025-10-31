<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ✅ Safely read POST values (avoid "Undefined array key" warnings)
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $supplier = isset($_POST['supplier']) ? trim($_POST['supplier']) : '';
    $total_quantity = isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0;
    $unit = isset($_POST['unit_of_measurement']) ? trim($_POST['unit_of_measurement']) : '';
    $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';
    $date = date('Y-m-d H:i:s');

    // ✅ Debugging: log missing values (for development use)
    // echo "<pre>"; print_r($_POST); echo "</pre>"; exit;

    // ✅ Validate required fields
    if ($project_id <= 0 || empty($name) || $total_quantity <= 0 || empty($unit)) {
        echo "<p style='color:red; font-family:Arial; margin:20px;'>Error: Missing or invalid required fields.</p>";
        exit;
    }

    // ✅ Automatically set remaining quantity to total quantity
    $remaining_quantity = $total_quantity;

    // ✅ Prepare SQL query
    $stmt = $conn->prepare("
        INSERT INTO materials 
        (project_id, name, supplier, total_quantity, remaining_quantity, unit_of_measurement, purpose, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        echo "<p style='color:red;'>Database prepare error: " . htmlspecialchars($conn->error) . "</p>";
        exit;
    }

    // ✅ Bind parameters properly
    $stmt->bind_param(
        "issddsss",
        $project_id,
        $name,
        $supplier,
        $total_quantity,
        $remaining_quantity,
        $unit,
        $purpose,
        $date
    );

    // ✅ Execute insert
    if ($stmt->execute()) {
        // Redirect back to project view after success
        header("Location: ../modules/view_project.php?id=$project_id");
        exit;
    } else {
        echo "<p style='color:red;'>Error adding material: " . htmlspecialchars($stmt->error) . "</p>";
    }

    $stmt->close();
}
?>
