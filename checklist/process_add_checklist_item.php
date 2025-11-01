<?php
// ============================================
// process_add_checklist_item.php — Final Safe Version (No Duplicates)
// ============================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

if (!is_admin()) {
    header("Location: ../modules/projects.php?status=access_denied");
    exit();
}

// --- 1. Validate Project ID ---
if (!isset($_POST['project_id']) || !is_numeric($_POST['project_id'])) {
    die('<h3 style="color:red;">Invalid project ID.</h3>');
}
$project_id = (int)$_POST['project_id'];

// --- 2. Validate Checklist Description ---
if (empty($_POST['item_description'])) {
    header("Location: ../modules/view_project.php?id=$project_id&status=checklist_item_added_error&message=" . urlencode("Checklist description cannot be empty."));
    exit();
}
$item_description = trim($_POST['item_description']);

// --- 3. Determine Apply Mode ---
$apply_mode = $_POST['apply_mode'] ?? 'single';
// Ensure unit_id is set to null if not provided/selected, as per bind_param compatibility
$unit_id = isset($_POST['unit_id']) && $_POST['unit_id'] !== '' ? (int)$_POST['unit_id'] : null;

// Array to track which units need progress recalculation
$units_to_recalculate = [];

try {
    $conn->begin_transaction();

    // --- 4. Prepare insert statement (assumes a table named 'project_checklists') ---
    $stmt = $conn->prepare("
        INSERT INTO project_checklists (project_id, unit_id, item_description, is_completed, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");

    // --- 5. Apply to All Units ---
    if ($apply_mode === 'all') {
        $units = $conn->query("SELECT id FROM project_units WHERE project_id = $project_id");
        while ($u = $units->fetch_assoc()) {
            $current_unit_id = (int)$u['id'];
            try {
                $stmt->bind_param("iis", $project_id, $current_unit_id, $item_description);
                $stmt->execute();
                // Add unit to recalculation list
                $units_to_recalculate[] = $current_unit_id;
            } catch (mysqli_sql_exception $e) {
                // Skip duplicates silently
                if ($e->getCode() == 1062) continue; 
                throw $e;
            }
        }
    } 
    // --- 6. Apply to single unit or general ---
    else {
        try {
            // Note: unit_id is already an integer or null
            $stmt->bind_param("iis", $project_id, $unit_id, $item_description);
            $stmt->execute();
            // Add unit to recalculation list if a specific unit was selected
            if ($unit_id !== null) {
                $units_to_recalculate[] = $unit_id;
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                // Duplicate entry detected
                $conn->rollback();
                header("Location: ../modules/view_project.php?id=$project_id&status=duplicate_item&message=" . urlencode("This checklist item already exists for this unit."));
                exit();
            } else {
                throw $e;
            }
        }
    }

    $stmt->close();


    // 💥 INSERTION START: Secure version of the held code block 💥
    // === STEP 7 : Recalculate progress for affected units ===
    if (!empty($units_to_recalculate)) {
        // Prepare statements outside the loop
        $total_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM project_checklists WHERE unit_id = ?");
        $done_stmt = $conn->prepare("SELECT COUNT(*) AS done FROM project_checklists WHERE unit_id = ? AND is_completed = 1");
        $update_stmt = $conn->prepare("UPDATE project_units SET progress = ? WHERE id = ?");

        foreach (array_unique($units_to_recalculate) as $recalc_unit_id) {
            // Count all checklist items for this unit
            $total_stmt->bind_param('i', $recalc_unit_id);
            $total_stmt->execute();
            $total = (int)($total_stmt->get_result()->fetch_assoc()['total'] ?? 0);

            // Count completed ones
            $done_stmt->bind_param('i', $recalc_unit_id);
            $done_stmt->execute();
            $done = (int)($done_stmt->get_result()->fetch_assoc()['done'] ?? 0);

            // Compute percentage
            $progress = $total > 0 ? round(($done / $total) * 100) : 0;

            // Update the project_units table
            $update_stmt->bind_param('ii', $progress, $recalc_unit_id);
            if (!$update_stmt->execute()) {
                 // Throwing an exception will trigger the catch block and rollback
                 throw new Exception("Failed to update unit progress for unit ID: $recalc_unit_id");
            }
        }
        
        $total_stmt->close();
        $done_stmt->close();
        $update_stmt->close();
    }
    // 💥 INSERTION END 💥

    $conn->commit();
    header("Location: ../modules/view_project.php?id=$project_id&tab=units&status=checklist_item_added_success"); // Direct to Units tab as progress might have changed
    exit();

} catch (Exception $e) {
    $conn->rollback();
    header("Location: ../modules/view_project.php?id=$project_id&status=checklist_item_added_error&message=" . urlencode($e->getMessage()));
    exit();
}
?>