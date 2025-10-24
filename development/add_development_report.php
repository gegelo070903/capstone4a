<?php
// add_development_report.php
// Secure handler to create a construction report with optional proof image and material usage rows.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

ensure_session_started();
require_login();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// CSRF check
require_csrf();

// Collect and validate inputs
$project_id   = post_int('project_id');
$report_date  = post_string('report_date');      // expected format: YYYY-MM-DD
$start_time   = post_string('start_time');       // expected format: HH:MM
$end_time     = post_string('end_time');         // expected format: HH:MM
$status       = post_string('status');           // 'complete' or 'ongoing'
$description  = post_string('description', '');

if (!$project_id || !$report_date || !$start_time || !$end_time || !$status) {
    flash_set('error', 'Missing required fields.');
    safe_redirect('/view_project.php?id=' . (int)$project_id);
}

// Enforce project access policy (admin or assigned constructor)
authorize_project_access($conn, (int)$project_id);

// Validate status
if (!in_array($status, ['complete','ongoing'], true)) {
    flash_set('error', 'Invalid status value.');
    safe_redirect('/view_project.php?id=' . (int)$project_id);
}

// Validate date and time formats lightly (server-side sanity checks)
if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $report_date)) {
    flash_set('error', 'Invalid date format.');
    safe_redirect('/view_project.php?id=' . (int)$project_id);
}
if (!preg_match('~^\d{2}:\d{2}$~', $start_time) || !preg_match('~^\d{2}:\d{2}$~', $end_time)) {
    flash_set('error', 'Invalid time format.');
    safe_redirect('/view_project.php?id=' . (int)$project_id);
}

// Prepare file upload (optional)
$proof_image_path = null;

if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
        flash_set('error', 'File upload failed.');
        safe_redirect('/view_project.php?id=' . (int)$project_id);
    }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($_FILES['proof_image']['size'] > $maxSize) {
        flash_set('error', 'File too large. Max 5MB.');
        safe_redirect('/view_project.php?id=' . (int)$project_id);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($_FILES['proof_image']['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed[$mime])) {
        flash_set('error', 'Unsupported file type. Use JPG, PNG, or WEBP.');
        safe_redirect('/view_project.php?id=' . (int)$project_id);
    }

    // Ensure destination directory exists
    $destDir = __DIR__ . '/uploads/reports_proofs';
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true)) {
            flash_set('error', 'Server cannot create upload directory.');
            safe_redirect('/view_project.php?id=' . (int)$project_id);
        }
    }

    // Randomized filename
    $basename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $destPath = $destDir . '/' . $basename;

    if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $destPath)) {
        flash_set('error', 'Could not save uploaded file.');
        safe_redirect('/view_project.php?id=' . (int)$project_id);
    }

    // Store relative path used by the app for display
    $proof_image_path = 'uploads/reports_proofs/' . $basename;
}

// Material usage arrays
$material_ids   = isset($_POST['material_id']) ? (array)$_POST['material_id'] : [];
$quantities_raw = isset($_POST['quantity_used']) ? (array)$_POST['quantity_used'] : [];

// Normalize material usage inputs
$usage = [];
for ($i = 0, $n = count($material_ids); $i < $n; $i++) {
    $mid = (int)$material_ids[$i];
    $qty = isset($quantities_raw[$i]) ? (string)$quantities_raw[$i] : '';
    if ($mid > 0 && $qty !== '') {
        // accept integers or decimals with 2 places
        if (!preg_match('~^\d+(?:\.\d{1,2})?$~', $qty)) {
            flash_set('error', 'Invalid quantity value for a material.');
            safe_redirect('/view_project.php?id=' . (int)$project_id);
        }
        $usage[] = ['material_id' => $mid, 'quantity_used' => (float)$qty];
    }
}

// Insert within a transaction
$conn->begin_transaction();

try {
    // Insert into construction_reports
    $stmt = $conn->prepare('
        INSERT INTO construction_reports
            (project_id, constructor_id, report_date, start_time, end_time, status, description, proof_image)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    if (!$stmt) {
        throw new Exception('Failed to prepare report insert.');
    }

    $constructor_id = current_user_id(); // constructor or admin submitting
    $desc = $description ?? '';
    $stmt->bind_param(
        'isssssss',
        $project_id,
        $constructor_id,
        $report_date,
        $start_time,
        $end_time,
        $status,
        $desc,
        $proof_image_path
    );
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert report.');
    }
    $report_id = (int)$stmt->insert_id;
    $stmt->close();

    // Insert material usage rows (no inventory deduction here; that flow remains separate)
    if (!empty($usage)) {
        $stmtU = $conn->prepare('
            INSERT INTO report_material_usage
                (report_id, material_id, quantity_used, is_deducted, created_at)
            VALUES
                (?, ?, ?, 0, NOW())
        ');
        if (!$stmtU) {
            throw new Exception('Failed to prepare usage insert.');
        }

        foreach ($usage as $row) {
            $mid = (int)$row['material_id'];
            $qty = (float)$row['quantity_used'];
            $stmtU->bind_param('iid', $report_id, $mid, $qty);
            if (!$stmtU->execute()) {
                throw new Exception('Failed to insert material usage.');
            }
        }
        $stmtU->close();
    }

    $conn->commit();
    flash_set('ok', 'Development report submitted successfully.');
    safe_redirect('/view_project.php?id=' . (int)$project_id);

} catch (Throwable $e) {
    $conn->rollback();

    // If we uploaded a file but failed later, try to remove it to avoid orphaned files
    if ($proof_image_path) {
        $abs = __DIR__ . '/' . $proof_image_path;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    // Avoid exposing internals to users
    flash_set('error', 'Could not save the report. Please try again.');
    safe_redirect('/view_project.php?id=' . (int)$project_id);
}
