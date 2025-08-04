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
    // Debug: Uncomment to see values
    // echo "Entered: $password, DB: " . $user['password'];
    // exit;
    if ($password === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid password!";
    }
}
}
?>
<!DOCTYPE html>
<html>
<head><title>Login</title></head>
<body>
<form method="POST">
    <h2>Login</h2>
    <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
    Username: <input name="username" required><br>
    Password: <input name="password" type="password" required><br>
    <button type="submit">Login</button>
</form>
</body>
</html>