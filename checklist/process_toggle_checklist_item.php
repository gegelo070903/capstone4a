<?php
// checklist/process_toggle_checklist_item.php
// Toggles a checklist item's completion status and updates progress.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

// ✅ Validate parameters
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit("<h3 style='color:red;'>Invalid checklist item ID.</h3>");
}
$item_id = intval($_GET['id']);

// --- Start Transaction ---
try {
    $conn->begin_transaction();

    // 1. Fetch item
    $stmt = $conn->prepare("SELECT * FROM project_checklists WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) {
        throw new Exception("Checklist item not found.");
    }

    $project_id = (int)$item['project_id'];
    $unit_id = (int)$item['unit_id'];

    // 2. Toggle status
    $new_status = $item['is_completed'] ? 0 : 1;
    $completed_at = $new_status ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("
        UPDATE project_checklists 
        SET is_completed = ?, completed_at = ?
        WHERE id = ?
    ");
    $stmt->bind_param("isi", $new_status, $completed_at, $item_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to toggle checklist status.");
    }
    $stmt->close();

    // --- Recalculate Unit Progress (SECURED VERSION OF HELD BLOCK PRINCIPLE) ---
    // Note: This logic only runs if the item is tied to a unit (unit_id is not 0 or null).
    if ($unit_id > 0) {
        // Count all checklist items for this unit
        $total_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM project_checklists WHERE unit_id = ?");
        $total_stmt->bind_param('i', $unit_id);
        $total_stmt->execute();
        $total_items = (int)($total_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $total_stmt->close();

        // Count completed ones
        $done_stmt = $conn->prepare("SELECT COUNT(*) AS done FROM project_checklists WHERE unit_id = ? AND is_completed = 1");
        $done_stmt->bind_param('i', $unit_id);
        $done_stmt->execute();
        $done_items = (int)($done_stmt->get_result()->fetch_assoc()['done'] ?? 0);
        $done_stmt->close();

        // Compute unit percentage
        $unit_progress = $total_items > 0 ? round(($done_items / $total_items) * 100) : 0;

        // Update the project_units table
        $update_unit_stmt = $conn->prepare("UPDATE project_units SET progress = ? WHERE id = ?");
        $update_unit_stmt->bind_param('ii', $unit_progress, $unit_id);
        if (!$update_unit_stmt->execute()) {
            throw new Exception("Failed to update unit progress.");
        }
        $update_unit_stmt->close();
    }
    
    // --- Recalculate Overall Project Progress (Based on Unit Averages) ---
    $progress_query = $conn->query("
        SELECT AVG(progress) AS avg_progress, COUNT(id) AS total_units 
        FROM project_units 
        WHERE project_id = $project_id
    ");
    $progress_data = $progress_query->fetch_assoc();
    
    $overall_progress = $progress_data['total_units'] > 0 
        ? round((float)$progress_data['avg_progress']) 
        : 0;

    // Update project table
    $update_project_stmt = $conn->prepare("UPDATE projects SET progress = ? WHERE id = ?");
    $update_project_stmt->bind_param('ii', $overall_progress, $project_id);
    if (!$update_project_stmt->execute()) {
        throw new Exception("Failed to update overall project progress.");
    }
    $update_project_stmt->close();

    // --- Update Project Status Based on Progress ---
    // If progress drops below 100%, change status from "Completed" back to "Ongoing"
    // If progress reaches 100%, change status to "Completed"
    $status_stmt = $conn->prepare("SELECT status FROM projects WHERE id = ?");
    $status_stmt->bind_param('i', $project_id);
    $status_stmt->execute();
    $current_status = $status_stmt->get_result()->fetch_assoc()['status'] ?? '';
    $status_stmt->close();

    if ($overall_progress >= 100 && $current_status !== 'Completed') {
        // Progress hit 100% - mark as Completed
        $new_status = 'Completed';
        $update_status_stmt = $conn->prepare("UPDATE projects SET status = ? WHERE id = ?");
        $update_status_stmt->bind_param('si', $new_status, $project_id);
        $update_status_stmt->execute();
        $update_status_stmt->close();
    } elseif ($overall_progress < 100 && $current_status === 'Completed') {
        // Progress dropped below 100% - revert to Ongoing
        $new_status = 'Ongoing';
        $update_status_stmt = $conn->prepare("UPDATE projects SET status = ? WHERE id = ?");
        $update_status_stmt->bind_param('si', $new_status, $project_id);
        $update_status_stmt->execute();
        $update_status_stmt->close();
    }


    // --- Commit and Redirect ---
    $conn->commit();
    // Log the toggle action
    $status_text = $new_status ? 'completed' : 'uncompleted';
    log_activity($conn, 'TOGGLE_CHECKLIST_ITEM', "Marked checklist item ID: $item_id as $status_text (Project ID: $project_id, Unit ID: $unit_id)");
    // Redirect back to the view checklist page or project view
    header("Location: ../modules/view_project.php?id=$project_id&tab=units&status=checklist_item_updated_success");
    exit();

} catch (Exception $e) {
    // --- Rollback and Error ---
    $conn->rollback();
    header("Location: ../modules/view_project.php?id=$project_id&tab=units&status=checklist_toggle_error&message=" . urlencode($e->getMessage()));
    exit();
}
?>