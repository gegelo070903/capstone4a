<?php
// materials/edit_material.php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

if (!isset($_GET['id']) || !isset($_GET['project_id'])) {
    die('<h3 style="color:red;">Invalid parameters provided.</h3>');
}

$id = intval($_GET['id']);
$project_id = intval($_GET['project_id']);

// Fetch material details for the form
$stmt = $conn->prepare("SELECT * FROM materials WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$material = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$material) {
    die('<h3 style="color:red;">Material not found.</h3>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    // Fetch total quantity from POST as the 'new' total quantity
    $new_total_quantity = floatval($_POST['total_quantity']);
    $unit = trim($_POST['unit_of_measurement']);
    $supplier = trim($_POST['supplier']);
    $purpose = trim($_POST['purpose']);
    
    // Set updated_at timestamp (Required for the UPDATE query)
    $updated_at = date('Y-m-d H:i:s');


    // ==========================================================
    // ✅ START: CALCULATE NEW REMAINING QUANTITY (From previous step)
    // ==========================================================

    // Fetch the current material data from DB just before updating
    $stmt = $conn->prepare("SELECT total_quantity, remaining_quantity FROM materials WHERE id = ? AND project_id = ?");
    $stmt->bind_param("ii", $id, $project_id);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$old) {
        // This should not happen if the first fetch worked, but is a safe guard.
        die("<p style='color:red;'>Error: Material not found for update calculation.</p>");
    }

    // Calculate used quantity
    $used_quantity = floatval($old['total_quantity']) - floatval($old['remaining_quantity']);
    if ($used_quantity < 0) $used_quantity = 0;

    // Calculate new remaining
    // Ensure the new remaining quantity doesn't go below zero
    $new_remaining_quantity = (int)($new_total_quantity - $used_quantity);
    if ($new_remaining_quantity < 0) $new_remaining_quantity = 0;

    // Round total to whole numbers for storage (matching previous logic)
    $new_total_quantity = (int)$new_total_quantity;

    // ==========================================================
    // ✅ END: CALCULATE NEW REMAINING QUANTITY
    // ==========================================================


    // ✅ Update material record
    $stmt = $conn->prepare("
        UPDATE materials 
        SET name = ?, supplier = ?, total_quantity = ?, remaining_quantity = ?, unit_of_measurement = ?, purpose = ?
        WHERE id = ? AND project_id = ?
    ");
    
    $final_total_quantity_db = $new_total_quantity;
    $final_remaining_quantity_db = $new_remaining_quantity;

    // NOTE: Changed bind_param type 'd' to 'i' based on your calculation rounding to (int)
    $stmt->bind_param(
        "ssiissii", // s:string, s:string, i:integer, i:integer, s:string, s:string, i:integer, i:integer 
        $name,
        $supplier,
        $final_total_quantity_db,
        $final_remaining_quantity_db,
        $unit,
        $purpose,
        $id,
        $project_id
    );

    if ($stmt->execute()) {
        // 🎯 UPDATED REDIRECT to include &tab=materials
        header("Location: ../modules/view_project.php?id=$project_id&tab=materials");
        exit;
    } else {
        echo "<h3 style='color:red;'>Error updating material: " . htmlspecialchars($stmt->error) . "</h3>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Material</title>
<style>
    body {
        font-family: "Segoe UI", Arial, sans-serif;
        background: #f9fafb;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
    }

    .edit-container {
        background: #fff;
        padding: 28px 36px;
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 640px;
    }

    h2 {
        text-align: center;
        color: #1f2937;
        margin-bottom: 20px;
        font-size: 22px;
    }

    .form-group {
        margin-bottom: 10px;
    }

    .form-row {
        display: flex;
        gap: 40px;
    }

    .form-row .form-group {
        flex: 1;
    }

    label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
        color: #374151;
        font-size: 14px;
    }

    input[type="text"],
    input[type="number"] {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 15px;
        transition: all 0.2s ease;
    }

    input[type="text"]:focus,
    input[type="number"]:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37,99,235,0.15);
        outline: none;
    }

    .btn-group {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 15px;
    }

    .btn-primary {
        background: #2563eb;
        color: #fff;
        border: none;
        padding: 9px 16px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.2s ease;
    }

    .btn-primary:hover {
        background: #1d4ed8;
    }

    .btn-cancel {
        background: #6b7280;
        color: #fff;
        border: none;
        padding: 9px 16px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.2s ease;
    }

    .btn-cancel:hover {
        background: #4b5563;
    }

    @media (max-width: 700px) {
        .edit-container {
            max-width: 90%;
            padding: 24px;
        }

        .form-row {
            flex-direction: column;
        }
    }
</style>
</head>
<body>
    <div class="edit-container">
        <h2>Edit Material</h2>
        <form method="POST">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($material['name']); ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="total_quantity">Total Quantity:</label>
                    <input type="number" id="total_quantity" name="total_quantity" value="<?= intval($material['total_quantity']); ?>" required>
                </div>

                <div class="form-group">
                    <!-- REMOVED: remaining_quantity is now calculated on the server side -->
                    <label for="remaining_quantity" style="color: #999;">Remaining Quantity (Calculated):</label>
                    <input type="number" id="remaining_quantity" value="<?= intval($material['remaining_quantity']); ?>" disabled style="background:#f4f4f4; color:#999;">
                    <!-- Note: If you really want to send a remaining_quantity, it should be a hidden field now -->
                </div>
            </div>

            <div class="form-group">
                <label for="unit_of_measurement">Unit of Measurement:</label>
                <input type="text" id="unit_of_measurement" name="unit_of_measurement" value="<?= htmlspecialchars($material['unit_of_measurement']); ?>" required>
            </div>

            <div class="form-group">
                <label for="supplier">Supplier:</label>
                <input type="text" id="supplier" name="supplier" value="<?= htmlspecialchars($material['supplier']); ?>">
            </div>

            <div class="form-group">
                <label for="purpose">Purpose:</label>
                <input type="text" id="purpose" name="purpose" value="<?= htmlspecialchars($material['purpose']); ?>">
            </div>

            <div class="btn-group">
                <!-- 🎯 UPDATED CANCEL BUTTON to include &tab=materials -->
                <button type="button" class="btn-cancel" onclick="window.location.href='../modules/view_project.php?id=<?= $project_id; ?>&tab=materials'">Cancel</button>
                <button type="submit" class="btn-primary">Update</button>
            </div>
        </form>
    </div>
</body>
</html>