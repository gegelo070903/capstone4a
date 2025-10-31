<?php
// ===============================================================
// includes/functions.php
// Unified Functions for Authentication, Flash Messages, and Helpers
// ===============================================================

// ---------------------------------------------------------------
// SESSION MANAGEMENT
// ---------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple helper to ensure session is always started safely
function ensure_session_started(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// ---------------------------------------------------------------
// HTML ESCAPING
// ---------------------------------------------------------------
function h($value): string {
    if (is_null($value)) return '';
    if (is_array($value)) return htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------
// AUTHENTICATION HELPERS
// ---------------------------------------------------------------

// ✅ Require login before proceeding
function require_login(): void {
    ensure_session_started();
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// ✅ Check if logged-in user is admin
function is_admin(): bool {
    ensure_session_started();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// ✅ Require admin access
function require_admin(): void {
    if (!is_admin()) {
        header('Location: dashboard.php?error=unauthorized');
        exit();
    }
}

// ✅ Get current user ID safely
function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

// ---------------------------------------------------------------
// PROJECT ACCESS AUTHORIZATION
// Restricts constructors to their assigned projects only.
// ---------------------------------------------------------------
function authorize_project_access(mysqli $conn, int $project_id): void {
    ensure_session_started();

    $user_id   = $_SESSION['user_id']   ?? null;
    $user_role = $_SESSION['user_role'] ?? '';

    // Admins always have access
    if ($user_role === 'admin') {
        return;
    }

    if (!$user_id || !$project_id) {
        http_response_code(400);
        exit('Invalid request.');
    }

    // Check if project belongs to the constructor
    $stmt = $conn->prepare('SELECT id FROM projects WHERE id = ? AND assigned_to = ? LIMIT 1');
    if (!$stmt) {
        error_log('authorize_project_access() prepare failed: ' . $conn->error);
        http_response_code(500);
        exit('Server error.');
    }

    $stmt->bind_param('ii', $project_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    $has_access = $stmt->num_rows > 0;
    $stmt->close();

    if (!$has_access) {
        http_response_code(403);
        exit('Forbidden: You are not authorized to access this project.');
    }
}

// ---------------------------------------------------------------
// FLASH MESSAGE SYSTEM
// ---------------------------------------------------------------
function flash_set(string $type, string $message): void {
    ensure_session_started();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_display(): void {
    ensure_session_started();
    if (!empty($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $msg  = h($_SESSION['flash']['message']);

        $color = match ($type) {
            'ok', 'success'   => '#d4edda',
            'error', 'danger' => '#f8d7da',
            'warning'         => '#fff3cd',
            default           => '#e2e3e5',
        };

        echo '<div style="margin:15px 0;padding:10px;border-radius:6px;background:' . $color . ';font-weight:600;">' . $msg . '</div>';
        unset($_SESSION['flash']);
    }
}

// Legacy alias (kept for compatibility)
function flash_message(): void {
    flash_display();
}

// ---------------------------------------------------------------
// REDIRECT HELPERS
// ---------------------------------------------------------------
function safe_redirect(string $url): void {
    header("Location: $url");
    exit();
}

function redirect_with_message(string $url, string $message): void {
    ensure_session_started();
    $_SESSION['flash'] = ['type' => 'info', 'message' => $message];
    header("Location: $url");
    exit();
}

// ---------------------------------------------------------------
// CSRF TOKEN HELPERS (Optional)
// ---------------------------------------------------------------
function generate_csrf_token(): string {
    ensure_session_started();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void {
    ensure_session_started();
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}


// ---------------------------------------------------------------
// ADDITIONAL HELPERS FOR REPORTS MODULE
// ---------------------------------------------------------------

// ✅ Generate CSRF token (alias for compatibility with add_report.php)
function csrf_token(): string {
    ensure_session_started();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ✅ Verify CSRF token validity
function verify_csrf_token(string $token): bool {
    ensure_session_started();
    // Use hash_equals for constant time comparison to mitigate timing attacks
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ✅ Ensure a directory exists (for image uploads)
function ensure_dir(string $path): void {
    // Check if the directory exists AND if it's writable/creatable
    if (!is_dir($path)) {
        // Use recursive option (true) and safe permissions (0755)
        if (!mkdir($path, 0755, true)) {
            error_log("Failed to create directory: $path");
        }
    }
}

// ✅ Clean file names (avoid special characters)
function safe_filename(string $name): string {
    // Replace sequences of non-alphanumeric, non-hyphen, non-underscore, non-dot characters with a single underscore
    $name = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $name);
    // Remove any leading/trailing dots or underscores
    return trim($name, '._');
}

// ✅ Save uploaded report images safely
function save_report_image(array $file, string $destDir): array {
    $allowed = [
        'image/jpeg' => '.jpg',
        'image/png'  => '.png',
        'image/webp' => '.webp'
    ];

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $code = $file['error'] ?? -1;
        // Provide more readable error messages for common codes
        $errorMessage = match($code) {
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in form.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            default               => "Upload error code $code"
        };
        return [false, $errorMessage];
    }

    // Use finfo to check the actual MIME type (more secure than just file extension)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        return [false, 'Unsupported file type: ' . $mime];
    }

    $ext = $allowed[$mime];
    $base = pathinfo($file['name'], PATHINFO_FILENAME);
    // Create a unique, timestamped file name
    $newName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . safe_filename($base) . $ext;

    ensure_dir($destDir);
    $target = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $newName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return [false, 'Failed to move file to destination: check permissions'];
    }

    return [true, $newName];
}

// ✅ Deduct material quantities safely (transaction-safe)
function deduct_material_quantity(mysqli $conn, int $materialId, int $qty): array {
    if ($qty <= 0) return [true, ''];

    // Start a transaction for integrity
    $conn->begin_transaction();

    try {
        // Lock the material row to prevent race conditions during read/update
        $stmt = $conn->prepare("SELECT remaining_quantity FROM materials WHERE id = ? FOR UPDATE");
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        $stmt->bind_param('i', $materialId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 0) throw new Exception('Material not found');

        $row = $result->fetch_assoc();
        $remaining = (int)$row['remaining_quantity'];

        if ($remaining < $qty) {
            throw new Exception('Insufficient stock: Available ' . $remaining . ', Requested ' . $qty);
        }

        $newRemaining = $remaining - $qty;
        $update = $conn->prepare("UPDATE materials SET remaining_quantity = ? WHERE id = ?");
        if (!$update) throw new Exception('Update prepare failed: ' . $conn->error);
        $update->bind_param('ii', $newRemaining, $materialId);

        if (!$update->execute()) throw new Exception('Failed to update material: ' . $update->error);
        $update->close();
        
        $conn->commit();
        return [true, ''];

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Deduct material error: " . $e->getMessage());
        return [false, $e->getMessage()];
    }
}