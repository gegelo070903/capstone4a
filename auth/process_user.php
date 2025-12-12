<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_login();

header('Content-Type: application/json');

// Only admins can process user actions
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized action.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
  // ---------------- ADD USER ----------------
  case 'add':
    // Only super_admin can add new users
    if (!is_super_admin()) {
        echo json_encode(['status' => 'danger', 'message' => 'Only Super Admin can add new users.']);
        break;
    }
    
    $username = trim($_POST['username'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
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
    $stmt = $conn->prepare("INSERT INTO users (username, display_name, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $display_name, $hashed, $role);
    $stmt->execute();
    
    $new_user_id = $conn->insert_id;
    
    // Log the activity
    log_activity($conn, 'ADD_ACCOUNT', "Added new user: $username (Role: $role, ID: $new_user_id)");

    // MODIFIED: Return new user data + action key
    echo json_encode([
        'status' => 'success',
        'message' => 'User added successfully',
        'action'  => 'add',
        'user' => [
            'id' => $new_user_id,
            'username' => $username,
            'display_name' => $display_name,
            'role' => $role
        ]
    ]);
    break;

  // ---------------- GET USER ----------------
  case 'get':
    $id = intval($_GET['id'] ?? 0);
    
    // Admin can only get their own account info
    if (!is_super_admin() && $id !== intval($_SESSION['user_id'] ?? 0)) {
        echo json_encode(['error' => 'Unauthorized']);
        break;
    }
    
    $stmt = $conn->prepare("SELECT id, username, display_name, role FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    echo json_encode($user);
    break;

  // ---------------- EDIT USER ----------------
  case 'edit':
    $id = intval($_POST['id'] ?? 0);
    
    // Admin can only edit their own account
    if (!is_super_admin() && $id !== intval($_SESSION['user_id'] ?? 0)) {
        echo json_encode(['status' => 'danger', 'message' => 'You can only edit your own account.']);
        break;
    }
    
    $username = trim($_POST['username'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $role = $_POST['role'] ?? 'constructor';
    $current_role = $_POST['current_role'] ?? '';
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
    
    // Admin cannot change their own role
    if (!is_super_admin()) {
        $role = $current_role;
    }
    
    // LOCKED: User ID 1 is always super_admin (the owner account)
    if ($id === 1) {
        $role = 'super_admin';
    }
    // Preserve super_admin role if user was super_admin
    elseif ($current_role === 'super_admin') {
        $role = 'super_admin';
    }

    if ($password) {
        $stmt = $conn->prepare("UPDATE users SET username=?, display_name=?, password=?, role=? WHERE id=?");
        $stmt->bind_param("ssssi", $username, $display_name, $password, $role, $id);
        $msg = 'User updated successfully — new password has been set.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=?, display_name=?, role=? WHERE id=?");
        $stmt->bind_param("sssi", $username, $display_name, $role, $id);
        $msg = 'User updated successfully — password unchanged.';
    }
    $stmt->execute();
    
    // Log the edit activity
    log_activity($conn, 'EDIT_ACCOUNT', "Edited user ID: $id, Username: $username, Role: $role");

    // MODIFIED: Return updated user data + action key
    echo json_encode([
        'status' => 'success',
        'message' => $msg,
        'action'  => 'edit',
        'user' => [
            'id' => $id,
            'username' => $username,
            'display_name' => $display_name,
            'role' => $role
        ]
    ]);
    break;

  // ---------------- DELETE USER ----------------
  case 'delete':
    // Only super_admin can delete users
    if (!is_super_admin()) {
        echo json_encode(['status' => 'danger', 'message' => 'Only Super Admin can delete users.']);
        break;
    }
    
    $id = intval($_POST['id'] ?? 0);

    // Prevent deletion of the super_admin owner account (ID 1)
    if ($id === 1) {
        echo json_encode(['status' => 'danger', 'message' => 'Cannot delete the owner account.']);
        break;
    }

    // Prevent admin from deleting themselves
    if ($id === ($_SESSION['user_id'] ?? 0)) {
        echo json_encode(['status' => 'danger', 'message' => 'Cannot delete your own account.']);
        break;
    }
    
    // Get username before deleting for logging
    $del_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $del_stmt->bind_param("i", $id);
    $del_stmt->execute();
    $del_result = $del_stmt->get_result();
    $del_user = $del_result->fetch_assoc();
    $deleted_username = $del_user['username'] ?? 'Unknown';
    $del_stmt->close();
    
    $conn->query("DELETE FROM users WHERE id=$id");
    
    // Log the delete activity
    log_activity($conn, 'DELETE_ACCOUNT', "Deleted user: $deleted_username (ID: $id)");

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