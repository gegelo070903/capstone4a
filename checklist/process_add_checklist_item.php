<?php
// ============================================
// process_add_checklist_item.php — Final Version
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

    if ($apply_mode === 'all') {
        // ✅ Apply checklist item to ALL UNITS in this project
        $units = $conn->query("SELECT id FROM project_units WHERE project_id = $project_id");
        while ($u = $units->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO project_checklists (project_id, unit_id, item_description, is_completed, created_at) VALUES (?, ?, ?, 0, NOW())");
            $stmt->bind_param("iis", $project_id, $u['id'], $item_description);
            $stmt->execute();
        }
    } else {
        // ✅ Apply only to selected unit or general (no unit)
        $stmt = $conn->prepare("INSERT INTO project_checklists (project_id, unit_id, item_description, is_completed, created_at) VALUES (?, ?, ?, 0, NOW())");
        $stmt->bind_param("iis", $project_id, $unit_id, $item_description);
        $stmt->execute();
    }

    $conn->commit();

    header("Location: ../modules/view_project.php?id=$project_id&status=checklist_item_added_success");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    header("Location: ../modules/view_project.php?id=$project_id&status=checklist_item_added_error&message=" . urlencode($e->getMessage()));
    exit();
}
