<?php
// ===============================================================
// process_delete_checklist_item.php
// Deletes a checklist item (admin-only).
// Called via fetch() in view_project.php; we return 204 or a tiny JSON.
// ===============================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_csrf();

// 1) Input
$id = post_int('id');
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid id.']);
    exit;
}

// 2) Enforce admin
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

// 3) Optional: ensure item exists (and grab project_id if you want to redirect)
$stmt = $conn->prepare('SELECT id FROM project_checklists WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->store_result();
$exists = $stmt->num_rows === 1;
$stmt->close();

if (!$exists) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found.']);
    exit;
}

// 4) Delete
$stmt = $conn->prepare('DELETE FROM project_checklists WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Delete failed.']);
    exit;
}

// Success — your page just reloads, so 204 is fine.
http_response_code(204);
