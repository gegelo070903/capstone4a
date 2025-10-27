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

// Fetch item
$stmt = $conn->prepare("SELECT * FROM project_checklists WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    exit("<h3 style='color:red;'>Checklist item not found.</h3>");
}

$project_id = (int)$item['project_id'];
$unit_id = (int)$item['unit_id'];

// ✅ Toggle status
$new_status = $item['is_completed'] ? 0 : 1;
$completed_at = $new_status ? date('Y-m-d H:i:s') : null;

$stmt = $conn->prepare("
    UPDATE project_checklists 
    SET is_completed = ?, completed_at = ?
    WHERE id = ?
");
$stmt->bind_param("isi", $new_status, $completed_at, $item_id);
$stmt->execute();
$stmt->close();

// ✅ Recalculate progress for this unit
$total_items = $conn->query("
    SELECT COUNT(*) AS total FROM project_checklists WHERE unit_id = $unit_id
")->fetch_assoc()['total'];

$done_items = $conn->query("
    SELECT COUNT(*) AS done FROM project_checklists WHERE unit_id = $unit_id AND is_completed = 1
")->fetch_assoc()['done'];

$unit_progress = $total_items ? round(($done_items / $total_items) * 100, 2) : 0;

// Store unit progress
$conn->query("
    UPDATE project_units SET progress = $unit_progress WHERE id = $unit_id
");

// ✅ Recalculate overall project progress
$total_checklists = $conn->query("
    SELECT COUNT(*) AS total FROM project_checklists WHERE project_id = $project_id
")->fetch_assoc()['total'];

$total_done = $conn->query("
    SELECT COUNT(*) AS done FROM project_checklists WHERE project_id = $project_id AND is_completed = 1
")->fetch_assoc()['done'];

$project_progress = $total_checklists ? round(($total_done / $total_checklists) * 100, 2) : 0;

// Update project table (optional if you want to show progress easily in listings)
$conn->query("
    UPDATE projects SET progress = $project_progress WHERE id = $project_id
");

// ✅ Redirect back
header("Location: ../modules/view_project.php?id=$project_id&status=checklist_item_updated_success");
exit();
