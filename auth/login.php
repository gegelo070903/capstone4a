<?php
// login.php (secure, drop-in replacement)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// bring in DB connection ($conn = new mysqli(...))
require_once __DIR__ . '/includes/db.php';

// Basic helpers
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// CSRF helpers
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function check_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $t = $_POST['csrf_token'] ?? '';
        $ref = $_SESSION['csrf_token'] ?? '';
        if (!$t || !$ref || !hash_equals($ref, $t)) {
            http_response_code(400);
            exit('Invalid CSRF token.');
        }
    }
}

// If already logged in, go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Handle POST (login)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        // Fetch user by username (prepared)
        $stmt = $conn->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
        if (!$stmt) {
            // Fail closed
            $error = 'Unexpected error. Please try again.';
        } else {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if ($user) {
                $stored = (string)$user['password'];

                $looksHashed = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2') || str_starts_with($stored, '$2a$');

                $ok = false;

                if ($looksHashed) {
                    // Modern path
                    $ok = password_verify($password, $stored);
                    // Rehash if needed (algorithm upgrades)
                    if ($ok && password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $up = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                        if ($up) {
                            $up->bind_param('si', $newHash, $user['id']);
                            $up->execute();
                            $up->close();
                        }
                    }
                } else {
                    // Backward-compat for existing plain-text passwords in DB
                    // Compare directly, then upgrade to hashed on success
                    if (hash_equals($stored, $password)) {
                        $ok = true;
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $up = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                        if ($up) {
                            $up->bind_param('si', $newHash, $user['id']);
                            $up->execute();
                            $up->close();
                        }
                    }
                }

                if ($ok) {
                    // Minimal session hardening
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = (int)$user['id'];
                    $_SESSION['username']  = (string)$user['username'];
                    $_SESSION['user_role'] = (string)$user['role'];

                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid credentials.';
                }
            } else {
                $error = 'Invalid credentials.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:#f6f7f9; margin:0; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .card { background:#fff; width:100%; max-width:380px; padding:24px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.08); }
    .title { font-size:20px; margin:0 0 8px; }
    .muted { color:#555; font-size:14px; margin:0 0 16px; }
    .group { margin-bottom:14px; }
    label { display:block; font-size:13px; margin-bottom:6px; color:#333; }
    input[type="text"], input[type="password"] { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; outline:none; font-size:14px; background:#fff; }
    input[type="text"]:focus, input[type="password"]:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,0.15); }
    .btn { width:100%; padding:10px 12px; border:0; background:#4f46e5; color:#fff; border-radius:8px; font-weight:600; cursor:pointer; }
    .btn:disabled { opacity:0.6; cursor:not-allowed; }
    .error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; padding:10px 12px; border-radius:8px; margin-bottom:14px; font-size:14px; }
    .footer { text-align:center; color:#6b7280; font-size:12px; margin-top:10px; }
</style>
</head>
<body>
    <div class="card">
        <h1 class="title">Sign in</h1>
        <p class="muted">Enter your credentials to access the dashboard.</p>

        <?php if ($error): ?>
            <div class="error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" autocomplete="off" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
            <div class="group">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" required autofocus>
            </div>
            <div class="group">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button class="btn" type="submit">Log in</button>
        </form>

        <div class="footer">Having trouble? Contact your administrator.</div>
    </div>
</body>
</html>
