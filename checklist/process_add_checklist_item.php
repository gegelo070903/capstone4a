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
$unit_id = isset($_POST['unit_id']) && $_POST['unit_id'] !== '' ? (int)$_POST['unit_id'] : null;

try {
    $conn->begin_transaction();

    // --- 4. Prepare insert statement ---
    $stmt = $conn->prepare("
        INSERT INTO project_checklists (project_id, unit_id, item_description, is_completed, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");

    // --- 5. Apply to All Units ---
    if ($apply_mode === 'all') {
        $units = $conn->query("SELECT id FROM project_units WHERE project_id = $project_id");
        while ($u = $units->fetch_assoc()) {
            try {
                $stmt->bind_param("iis", $project_id, $u['id'], $item_description);
                $stmt->execute();
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
            $stmt->bind_param("iis", $project_id, $unit_id, $item_description);
            $stmt->execute();
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

    $conn->commit();
    header("Location: ../modules/view_project.php?id=$project_id&status=checklist_item_added_success");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    header("Location: ../modules/view_project.php?id=$project_id&status=checklist_item_added_error&message=" . urlencode($e->getMessage()));
    exit();
}
?>
