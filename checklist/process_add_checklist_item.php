<?php
// ============================================
// process_add_checklist_item.php
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

// --- 3. Validate Unit ID ---
if (!isset($_POST['unit_id']) || $_POST['unit_id'] === '') {
    header("Location: ../modules/view_project.php?id=$project_id&status=checklist_item_added_error&message=" . urlencode("Please select a unit."));
    exit();
}

$unit_selection = $_POST['unit_id'];
$apply_to_all = ($unit_selection === 'all');

// Array to track which units need progress recalculation
$units_to_recalculate = [];

try {
    $conn->begin_transaction();

    // --- 4. Prepare insert statement ---
    $stmt = $conn->prepare("
        INSERT INTO project_checklists (project_id, unit_id, item_description, is_completed, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");

    // --- 5. Apply to All Units ---
    if ($apply_to_all) {
        $units = $conn->query("SELECT id FROM project_units WHERE project_id = $project_id");
        while ($u = $units->fetch_assoc()) {
            $current_unit_id = (int)$u['id'];
            try {
                $stmt->bind_param("iis", $project_id, $current_unit_id, $item_description);
                $stmt->execute();
                $units_to_recalculate[] = $current_unit_id;
            } catch (mysqli_sql_exception $e) {
                // Skip duplicates silently
                if ($e->getCode() == 1062) continue; 
                throw $e;
            }
        }
    } 
    // --- 6. Apply to single unit ---
    else {
        $unit_id = (int)$unit_selection;
        try {
            $stmt->bind_param("iis", $project_id, $unit_id, $item_description);
            $stmt->execute();
            $units_to_recalculate[] = $unit_id;
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $conn->rollback();
                header("Location: ../modules/view_project.php?id=$project_id&status=duplicate_item&message=" . urlencode("This checklist item already exists for this unit."));
                exit();
            } else {
                throw $e;
            }
        }
    }

    $stmt->close();

    // --- 7. Recalculate progress for affected units ---
    if (!empty($units_to_recalculate)) {
        $total_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM project_checklists WHERE unit_id = ?");
        $done_stmt = $conn->prepare("SELECT COUNT(*) AS done FROM project_checklists WHERE unit_id = ? AND is_completed = 1");
        $update_stmt = $conn->prepare("UPDATE project_units SET progress = ? WHERE id = ?");

        foreach (array_unique($units_to_recalculate) as $recalc_unit_id) {
            $total_stmt->bind_param('i', $recalc_unit_id);
            $total_stmt->execute();
            $total = (int)($total_stmt->get_result()->fetch_assoc()['total'] ?? 0);

            $done_stmt->bind_param('i', $recalc_unit_id);
            $done_stmt->execute();
            $done = (int)($done_stmt->get_result()->fetch_assoc()['done'] ?? 0);

            $progress = $total > 0 ? round(($done / $total) * 100) : 0;

            $update_stmt->bind_param('ii', $progress, $recalc_unit_id);
            $update_stmt->execute();
        }
        
        $total_stmt->close();
        $done_stmt->close();
        $update_stmt->close();
    }

    $conn->commit();
    header("Location: ../modules/view_project.php?id=$project_id&tab=units&status=checklist_item_added_success");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    header("Location: ../modules/view_project.php?id=$project_id&status=checklist_item_added_error&message=" . urlencode($e->getMessage()));
    exit();
}
?>