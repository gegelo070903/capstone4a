<?php
// ===============================================================
// process_delete_checklist_item.php
// Deletes a checklist item (admin-only) and recalculates progress.
// Returns 204 or JSON response.
// ===============================================================

// ---------------------------------------------------------------
// 0) Include dependencies
// ---------------------------------------------------------------
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../auth/functions.php';

// ---------------------------------------------------------------
// 0.1) Define lightweight helper functions if missing
// ---------------------------------------------------------------
if (!function_exists('require_csrf')) {
    function require_csrf(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $t   = $_POST['csrf_token'] ?? '';
            $ref = $_SESSION['csrf_token'] ?? '';
            if (!$t || !$ref || !hash_equals($ref, $t)) {
                http_response_code(400);
                exit(json_encode(['success' => false, 'message' => 'Invalid CSRF token.']));
            }
        }
    }
}
if (!function_exists('post_int')) {
    function post_int(string $key): ?int {
        if (!isset($_POST[$key])) return null;
        $v = trim((string)$_POST[$key]);
        if ($v === '' || !ctype_digit($v)) return null;
        return (int)$v;
    }
}

// ---------------------------------------------------------------
// 1) Security: Login + CSRF protection
// ---------------------------------------------------------------
require_login();
require_csrf();

// ---------------------------------------------------------------
// 2) Input validation
// ---------------------------------------------------------------
header('Content-Type: application/json');
$id = post_int('id');
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid id.']);
    exit;
}

// ---------------------------------------------------------------
// 3) Authorization: Only admins can delete items
// ---------------------------------------------------------------
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

// ---------------------------------------------------------------
// 4) Integrity: Start Transaction & Fetch IDs BEFORE Delete
// ---------------------------------------------------------------
$project_id = 0;
$unit_id = 0;

try {
    $conn->begin_transaction();
    
    // Fetch IDs needed for recalculation
    $stmt = $conn->prepare('SELECT project_id, unit_id FROM project_checklists WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();

    if (!$item) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Not found.']);
        exit;
    }
    
    $project_id = (int)$item['project_id'];
    $unit_id = (int)$item['unit_id']; // This could be 0 or null for a general checklist

    // ---------------------------------------------------------------
    // 5) Delete the checklist item
    // ---------------------------------------------------------------
    $stmt = $conn->prepare('DELETE FROM project_checklists WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new Exception('Delete failed.');
    }
    $stmt->close();
    
    // ---------------------------------------------------------------
    // 6) Recalculate Unit Progress (if unit_id is valid)
    // ---------------------------------------------------------------
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
    
    // ---------------------------------------------------------------
    // 7) Recalculate Overall Project Progress (Based on Unit Averages)
    // ---------------------------------------------------------------
    // Use prepared statement principles for fetching (though dynamic SQL is fine here)
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

    // ---------------------------------------------------------------
    // 8) Commit and Success
    // ---------------------------------------------------------------
    $conn->commit();
    http_response_code(204);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Operation failed: ' . $e->getMessage()]);
    exit;
}
?>