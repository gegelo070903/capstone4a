<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';

// Helpers
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// CSRF
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

// Redirect if logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($user) {
            $stored = $user['password'];
            $ok = password_verify($password, $stored) ||
                (!str_starts_with($stored, '$2y$') && hash_equals($stored, $password));

            if ($ok) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];

                if (!str_starts_with($stored, '$2y$')) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $up = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $up->bind_param('si', $newHash, $user['id']);
                    $up->execute();
                    $up->close();
                }

                header('Location: ../dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | Sunshine Sapphire Construction</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root {
  --blue: #2563eb;
  --blue-dark: #1e3a8a;
  --blue-light: #93c5fd;
  --white: #ffffff;
  --gray: #6b7280;
  --error-bg: #fee2e2;
  --error-border: #fecaca;
  --error-text: #991b1b;
}
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  height: 100vh;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #60a5fa 100%);
  overflow: hidden;
  position: relative;
}

/* Decorative background elements */
body::before, body::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  filter: blur(90px);
  opacity: 0.25;
  z-index: 0;
}
body::before {
  width: 500px;
  height: 500px;
  background: #93c5fd;
  top: -150px;
  right: -150px;
}
body::after {
  width: 400px;
  height: 400px;
  background: #1e40af;
  bottom: -100px;
  left: -100px;
}

/* Login card */
.card {
  position: relative;
  z-index: 2;
  background: var(--white);
  width: 100%;
  max-width: 420px;
  border-radius: 16px;
  padding: 40px 36px;
  box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
  animation: fadeIn 0.5s ease;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(15px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Header */
.logo {
  display: block;
  margin: 0 auto 18px;
  width: 100px;
  height: auto;
}
h1 {
  text-align: center;
  font-size: 24px;
  color: #1e293b;
  margin-bottom: 6px;
}
.subtitle {
  text-align: center;
  font-size: 14px;
  color: var(--gray);
  margin-bottom: 24px;
}

/* Form */
label {
  display: block;
  font-weight: 600;
  font-size: 14px;
  color: #374151;
  margin-bottom: 6px;
}
input[type="text"], input[type="password"] {
  width: 100%;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  font-size: 14px;
  transition: border-color 0.2s, box-shadow 0.2s;
}
input:focus {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
}
.form-group { margin-bottom: 18px; }

/* Button */
.btn {
  width: 100%;
  background: var(--blue);
  color: var(--white);
  border: none;
  border-radius: 8px;
  padding: 12px;
  font-weight: 600;
  font-size: 15px;
  cursor: pointer;
  transition: background 0.25s ease;
}
.btn:hover { background: var(--blue-dark); }

/* Error */
.error {
  background: var(--error-bg);
  border: 1px solid var(--error-border);
  color: var(--error-text);
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 14px;
  margin-bottom: 18px;
}

/* Footer */
.footer {
  text-align: center;
  color: var(--gray);
  font-size: 12px;
  margin-top: 20px;
}
</style>
</head>
<body>
  <div class="card">
    <img src="../assets/images/logo.png" alt="Company Logo" class="logo">
    <h1>Welcome Back</h1>
    <p class="subtitle">Sign in to access your dashboard</p>

    <?php if ($error): ?>
      <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php" autocomplete="off" novalidate>
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']); ?>">

      <div class="form-group">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" required autofocus>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>
      </div>

      <button class="btn" type="submit">Sign In</button>
    </form>

    <div class="footer">© <?= date('Y') ?> Sunshine Sapphire Construction</div>
  </div>
</body>
</html>
