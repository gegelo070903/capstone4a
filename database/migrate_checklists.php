<?php
// ===============================================================
// migrate_checklists.php
// Moves or deletes legacy checklist items (unit_id=NULL) for a project
// ===============================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
if (!is_admin()) { die('Forbidden'); }

// Inputs
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$unit_id    = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;
$action     = isset($_GET['action']) ? $_GET['action'] : 'preview'; // move | delete | preview

if (!$project_id) {
    die('Usage: migrate_checklists.php?project_id=1&action=move&unit_id=5 OR &action=delete');
}

echo "<h2>Checklist Migration Utility</h2>";
echo "<p>Project ID: {$project_id}</p>";

// Fetch legacy (no-unit) items
$stmt = $conn->prepare("SELECT id, item_description, is_completed, unit_id FROM project_checklists WHERE project_id=? AND unit_id IS NULL");
$stmt->bind_param('i', $project_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$rows) {
    echo "<p>No orphan checklist items found.</p>";
    exit;
}

// Perform chosen action
if ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM project_checklists WHERE project_id=? AND unit_id IS NULL");
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    echo "<p style='color:red;'>Deleted {$stmt->affected_rows} old checklist item(s).</p>";
    $stmt->close();
}
elseif ($action === 'move' && $unit_id) {
    $stmt = $conn->prepare("UPDATE project_checklists SET unit_id=? WHERE project_id=? AND unit_id IS NULL");
    $stmt->bind_param('ii', $unit_id, $project_id);
    $stmt->execute();
    echo "<p style='color:green;'>Moved {$stmt->affected_rows} old checklist item(s) into unit ID #{$unit_id}.</p>";
    $stmt->close();
}
else {
    echo "<p style='color:orange;'>Preview mode only. Add &action=delete or &action=move&unit_id=X to apply.</p>";
}

// Display affected items
echo "<table border='1' cellpadding='6' style='border-collapse:collapse;margin-top:10px;'>
        <tr><th>ID</th><th>Description</th><th>Completed</th><th>Unit</th></tr>";
foreach ($rows as $r) {
    echo "<tr>
            <td>{$r['id']}</td>
            <td>" . htmlspecialchars($r['item_description']) . "</td>
            <td>" . ($r['is_completed'] ? '✅' : '❌') . "</td>
            <td>" . ($r['unit_id'] ?? 'NULL') . "</td>
          </tr>";
}
echo "</table>";
?>

