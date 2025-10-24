<?php
// ===============================================================
// process_add_checklist_item.php
// Creates a checklist item for a specific house/unit in a project
// Security: login required, CSRF, server-side validation, auth check
// ===============================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_csrf();

// ---------------------------------------------------------------
// 1) Gather and validate POST input
// ---------------------------------------------------------------
$project_id      = post_int('project_id');
$unit_id         = post_int('unit_id');                 // required (per-house checklist)
$item_description= post_string('item_description');     // required

if (!$project_id || !$unit_id || !$item_description) {
    // Minimal error path; keep the user experience simple
    header('Location: /capstone/view_project.php?id=' . (int)$project_id . '&status=checklist_item_added_error&message=Missing+required+fields');
    exit;
}

// ---------------------------------------------------------------
// 2) Authorization: Only admins (or extend to constructor if desired)
//    You display the button to admins only; enforce that here too.
// ---------------------------------------------------------------
if (!is_admin()) {
    http_response_code(403);
    exit('Forbidden: Only admins can add checklist items.');
}

// Optional: If you’d like to allow assigned constructor too, replace the block above with:
// authorize_project_access($conn, $project_id);

// ---------------------------------------------------------------
// 3) Integrity check: ensure the unit belongs to the project
//    Prevent tampering (posting a unit_id from another project)
// ---------------------------------------------------------------
$ok_unit = false;
$stmt = $conn->prepare('SELECT id FROM project_units WHERE id = ? AND project_id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('ii', $unit_id, $project_id);
    $stmt->execute();
    $stmt->store_result();
    $ok_unit = $stmt->num_rows === 1;
    $stmt->close();
}
if (!$ok_unit) {
    header('Location: /capstone/view_project.php?id=' . (int)$project_id . '&status=checklist_item_added_error&message=Invalid+unit');
    exit;
}

// ---------------------------------------------------------------
// 4) Insert checklist item (default is_completed = 0)
// ---------------------------------------------------------------
$stmt = $conn->prepare('
    INSERT INTO project_checklists
        (project_id, unit_id, item_description, is_completed, created_at)
    VALUES
        (?, ?, ?, 0, NOW())
');
if (!$stmt) {
    error_log('prepare failed: ' . $conn->error);
    header('Location: /capstone/view_project.php?id=' . (int)$project_id . '&status=checklist_item_added_error');
    exit;
}

$stmt->bind_param('iis', $project_id, $unit_id, $item_description);

if (!$stmt->execute()) {
    error_log('insert failed: ' . $stmt->error);
    $stmt->close();
    header('Location: /capstone/view_project.php?id=' . (int)$project_id . '&status=checklist_item_added_error');
    exit;
}

$stmt->close();

// ---------------------------------------------------------------
// 5) Success -> redirect back to project view
// ---------------------------------------------------------------
header('Location: /capstone/view_project.php?id=' . (int)$project_id . '&status=checklist_item_added_success');
exit;
