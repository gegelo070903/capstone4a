<?php
session_start();
include 'includes/db.php';

$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $user = $result->fetch_assoc()) {
        if ($password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sunshine Sapphire Construction</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* All the CSS from the previous step remains exactly the same */
        body {
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        .login-container {
            background-color: #ffffff;
            padding: 30px 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .login-header { margin-bottom: 20px; }
        .logo { max-width: 120px; height: auto; margin-bottom: 10px; }
        .login-title { font-size: 2.5rem; font-weight: bold; margin-bottom: 5px; margin-top: 0; }
        .login-subtitle { color: #6c757d; margin-bottom: 25px; font-size: 1.1rem; margin-top: 0; }
        .login-form { text-align: left; }
        .form-group { margin-bottom: 15px; }
        .password-wrapper { position: relative; display: flex; align-items: center; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #ced4da; border-radius: 6px; box-sizing: border-box; }
        #password { padding-right: 40px; }
        .toggle-password-icon { position: absolute; right: 15px; cursor: pointer; color: #888; }
        .login-button { width: 100%; padding: 12px; background-color: #000000; color: #ffffff; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: bold; margin-top: 10px; }
        .login-button:hover { background-color: #333; }
        .error-message { background-color: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 6px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="images/Sunshine Sapphire Construction and Supply Logo.png" alt="Sunshine Sapphire Logo" class="logo">
            <h1 class="login-title">Login</h1>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="username" class="form-label">Username:</label>
                <input type="text" id="username" name="username" class="form-input" placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label for="password" class="form-label">Password:</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
                    <i class="fas fa-eye toggle-password-icon" id="togglePassword"></i>
                </div>
            </div>
            <button type="submit" class="login-button">Login</button>
        </form>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>