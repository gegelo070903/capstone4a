<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
  // ---------------- ADD USER ----------------
  case 'add':
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

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

    echo json_encode(['status' => 'success', 'message' => 'User added successfully']);
    break;

  // ---------------- GET USER ----------------
  case 'get':
    $id = intval($_GET['id']);
    $res = $conn->query("SELECT id, username, role FROM users WHERE id=$id");
    echo json_encode($res->fetch_assoc());
    break;

  // ---------------- EDIT USER ----------------
  case 'edit':
    $id = intval($_POST['id']);
    $username = trim($_POST['username']);
    $role = $_POST['role'];
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

    echo json_encode(['status' => 'success', 'message' => $msg]);
    break;

  // ---------------- DELETE USER ----------------
  case 'delete':
    $id = intval($_POST['id']);
    $conn->query("DELETE FROM users WHERE id=$id");
    echo json_encode(['status' => 'success', 'message' => 'User deleted successfully']);
    break;

  // ---------------- INVALID ACTION ----------------
  default:
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request']);
}
