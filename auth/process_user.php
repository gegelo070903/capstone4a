<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

header('Content-Type: application/json');

// Only admins can process user actions
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized action.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
  // ---------------- ADD USER ----------------
  case 'add':
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'constructor';

    if (empty($username) || empty($password)) {
      echo json_encode(['status' => 'danger', 'message' => 'Username and password are required']);
      exit;
    }

    // prevent duplicates
    $check = $conn->prepare("SELECT id FROM users WHERE username=?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
      echo json_encode(['status' => 'danger', 'message' => 'Username already exists']);
      exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $hashed, $role);
    $stmt->execute();

    // MODIFIED: Return new user data + action key
    echo json_encode([
        'status' => 'success',
        'message' => 'User added successfully',
        'action'  => 'add',
        'user' => [
            'id' => $conn->insert_id,
            'username' => $username,
            'role' => $role
        ]
    ]);
    break;

  // ---------------- GET USER ----------------
  case 'get':
    $id = intval($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    echo json_encode($user);
    break;

  // ---------------- EDIT USER ----------------
  case 'edit':
    $id = intval($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? 'constructor';
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

    if ($password) {
        $stmt = $conn->prepare("UPDATE users SET username=?, password=?, role=? WHERE id=?");
        $stmt->bind_param("sssi", $username, $password, $role, $id);
        $msg = 'User updated successfully — new password has been set.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=?, role=? WHERE id=?");
        $stmt->bind_param("ssi", $username, $role, $id);
        $msg = 'User updated successfully — password unchanged.';
    }
    $stmt->execute();

    // MODIFIED: Return updated user data + action key
    echo json_encode([
        'status' => 'success',
        'message' => $msg,
        'action'  => 'edit',
        'user' => [
            'id' => $id,
            'username' => $username,
            'role' => $role
        ]
    ]);
    break;

  // ---------------- DELETE USER ----------------
  case 'delete':
    $id = intval($_POST['id'] ?? 0);

    // Prevent admin from deleting themselves
    if ($id === ($_SESSION['user_id'] ?? 0)) {
        echo json_encode(['status' => 'danger', 'message' => 'Cannot delete your own account.']);
        break;
    }
    
    $conn->query("DELETE FROM users WHERE id=$id");

    // MODIFIED: Return deleted ID + action key
    echo json_encode([
        'status' => 'success',
        'message' => 'User deleted successfully',
        'action'  => 'delete',
        'user_id' => $id
    ]);
    break;

  // ---------------- INVALID ACTION ----------------
  default:
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request']);
}