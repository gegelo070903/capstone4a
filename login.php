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
/* --- Start of updated CSS --- */

body {
    background-color: #f0f2f5;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    padding: 20px;
    box-sizing: border-box;
    position: relative;
    overflow: hidden; 
}

body::before {
    content: '';
    position: absolute;
    top: -20%;
    left: -15%;
    width: 500px;
    height: 500px;
    background-image: linear-gradient(135deg, #007bff, #c2e9fb);
    border-radius: 50%;
    opacity: 0.4;
    z-index: -1;
    filter: blur(50px);
}

body::after {
    content: '';
    position: absolute;
    bottom: -25%;
    right: -20%;
    width: 500px;
    height: 500px;
    background-image: linear-gradient(135deg, #ffc107, #fff1c9);
    border-radius: 50%;
    opacity: 0.3;
    z-index: -1;
    filter: blur(60px);
}

.login-container {
    background-color: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    padding: 25px 60px;
    border-radius: 15px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
    text-align: center;
    max-width: 500px;
    width: 100%;
    box-sizing: border-box;
    z-index: 1;
}

.login-header {
    /* --- EDITED THIS LINE --- */
    margin-bottom: 15px; /* Reduced space below the header */
}

.logo {
    max-width: 140px;
    height: auto;
    margin-bottom: 15px;
}

.login-title {
    font-size: 2.2rem;
    font-weight: bold;
    margin: 0;
    color: #333;
}

.login-form {
    text-align: left;
}

.form-group {
    margin-bottom: 20px;
}

.password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #555;
}

.form-input {
    width: 100%;
    padding: 14px;
    font-size: 1.1rem;
    border: 1px solid #ced4da;
    border-radius: 8px;
    box-sizing: border-box;
    transition: border-color 0.3s, box-shadow 0.3s;
}

.form-input:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
    outline: none;
}

#password {
    padding-right: 45px;
}

.toggle-password-icon {
    position: absolute;
    right: 15px;
    cursor: pointer;
    color: #888;
    font-size: 1.2rem;
}

.login-button {
    width: 100%;
    padding: 15px;
    background-color: #000000;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1.1rem;
    font-weight: bold;
    margin-top: 10px;
    transition: background-color 0.3s ease;
}

.login-button:hover {
    background-color: #333;
}

.error-message {
    background-color: #f8d7da;
    color: #721c24;
    padding: 12px;
    border: 1px solid #f5c6cb;
    border-radius: 6px;
    margin-bottom: 20px;
    text-align:center;
}

/* --- End of updated CSS --- */
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