<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

header('Content-Type: application/json'); // Respond with JSON

$response = ['success' => false, 'message' => ''];

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !is_admin()) {
    $response['message'] = "Unauthorized access.";
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rmu_id = isset($_POST['rmu_id']) ? intval($_POST['rmu_id']) : 0;
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0; // For redirection/context

    if ($rmu_id <= 0 || $project_id <= 0) {
        $response['message'] = "Invalid deduction request (Missing RMU ID or Project ID).";
        echo json_encode($response);
        exit();
    }

    // Start a transaction for atomicity
    $conn->begin_transaction();

    try {
        // 1. Fetch the material usage details from report_material_usage
        $stmt_fetch = $conn->prepare("SELECT rmu.material_id, rmu.quantity_used, rmu.is_deducted, m.quantity AS current_stock
                                     FROM report_material_usage rmu
                                     JOIN materials m ON rmu.material_id = m.id
                                     WHERE rmu.id = ?");
        $stmt_fetch->bind_param("i", $rmu_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        $usage_data = $result_fetch->fetch_assoc();
        $stmt_fetch->close();

        if (!$usage_data) {
            throw new Exception("Material usage record not found.");
        }
        if ($usage_data['is_deducted']) {
            throw new Exception("This material quantity has already been deducted.");
        }

        $material_id = $usage_data['material_id'];
        $quantity_to_deduct = $usage_data['quantity_used'];
        $current_stock = $usage_data['current_stock'];

        // 2. Check if there's enough stock to deduct
        if ($current_stock < $quantity_to_deduct) {
            throw new Exception("Insufficient stock for material ID " . $material_id . ". Available: " . $current_stock . ", Attempted deduction: " . $quantity_to_deduct);
        }

        // 3. Deduct the quantity from the main materials table
        $stmt_deduct = $conn->prepare("UPDATE materials SET quantity = quantity - ? WHERE id = ?");
        $stmt_deduct->bind_param("di", $quantity_to_deduct, $material_id);
        if (!$stmt_deduct->execute()) {
            throw new Exception("Failed to deduct from main inventory: " . $stmt_deduct->error);
        }
        $stmt_deduct->close();

        // 4. Mark the report_material_usage as deducted
        $deducted_by_user_id = $_SESSION['user_id'];
        $deducted_at = date('Y-m-d H:i:s');
        $stmt_update_rmu = $conn->prepare("UPDATE report_material_usage SET is_deducted = TRUE, deducted_by_user_id = ?, deducted_at = ? WHERE id = ?");
        $stmt_update_rmu->bind_param("isi", $deducted_by_user_id, $deducted_at, $rmu_id);
        if (!$stmt_update_rmu->execute()) {
            throw new Exception("Failed to mark usage as deducted: " . $stmt_update_rmu->error);
        }
        $stmt_update_rmu->close();

        $conn->commit();
        $response['success'] = true;
        $response['message'] = "Material deducted successfully.";

    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = "Deduction failed: " . $e->getMessage();
    }

} else {
    $response['message'] = "Invalid request method.";
}

echo json_encode($response);
exit();
?>