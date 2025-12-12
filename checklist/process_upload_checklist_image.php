<?php
// checklist/process_upload_checklist_image.php (Handles UPLOAD, REMOVE, and multiple images)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

header('Content-Type: application/json');

$checklist_id = filter_input(INPUT_POST, 'checklist_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? 'upload'; // Default action is upload

function respond_json($success, $message, $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

if (!$checklist_id) {
    respond_json(false, 'Invalid checklist ID provided.');
}

$upload_dir = __DIR__ . '/../uploads/checklist_proofs/';

// ====================================================================
// ✅ HANDLE REMOVE SINGLE IMAGE ACTION (by image ID)
// ====================================================================

if ($action === 'remove') {
    $image_id = filter_input(INPUT_POST, 'image_id', FILTER_VALIDATE_INT);
    
    if (!$image_id) {
        respond_json(false, 'Invalid image ID provided.');
    }
    
    try {
        $conn->begin_transaction();

        // 1. Fetch the image path
        $stmt = $conn->prepare("SELECT image_path FROM checklist_images WHERE id = ? AND checklist_id = ?");
        if (!$stmt) throw new Exception('DB Error (Prepare Fetch): ' . $conn->error);
        $stmt->bind_param("ii", $image_id, $checklist_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$item) throw new Exception('Image not found.');

        $old_file = $item['image_path'];

        // 2. Delete from database
        $stmt = $conn->prepare("DELETE FROM checklist_images WHERE id = ? AND checklist_id = ?");
        if (!$stmt) throw new Exception('DB Error (Prepare Delete): ' . $conn->error);
        $stmt->bind_param("ii", $image_id, $checklist_id);
        if (!$stmt->execute()) throw new Exception('Failed to delete from database: ' . $stmt->error);
        $stmt->close();

        // 3. Delete the file from the filesystem
        if (!empty($old_file)) {
            $file_path = $upload_dir . $old_file;
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }

        $conn->commit();
        log_activity($conn, 'REMOVE_CHECKLIST_IMAGE', "Removed checklist proof image (Image ID: $image_id, Checklist ID: $checklist_id)");
        respond_json(true, 'Image removed successfully!');

    } catch (Exception $e) {
        $conn->rollback();
        respond_json(false, 'Server Error: ' . $e->getMessage());
    }
}

// ====================================================================
// ✅ HANDLE REMOVE ALL IMAGES ACTION
// ====================================================================

if ($action === 'remove_all') {
    try {
        $conn->begin_transaction();

        // 1. Fetch all image paths
        $stmt = $conn->prepare("SELECT image_path FROM checklist_images WHERE checklist_id = ?");
        if (!$stmt) throw new Exception('DB Error (Prepare Fetch): ' . $conn->error);
        $stmt->bind_param("i", $checklist_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $images = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // 2. Delete all from database
        $stmt = $conn->prepare("DELETE FROM checklist_images WHERE checklist_id = ?");
        if (!$stmt) throw new Exception('DB Error (Prepare Delete): ' . $conn->error);
        $stmt->bind_param("i", $checklist_id);
        if (!$stmt->execute()) throw new Exception('Failed to delete from database: ' . $stmt->error);
        $stmt->close();

        // 3. Delete files from filesystem
        foreach ($images as $img) {
            if (!empty($img['image_path'])) {
                $file_path = $upload_dir . $img['image_path'];
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
        }

        $conn->commit();
        log_activity($conn, 'REMOVE_CHECKLIST_IMAGES', "Removed all checklist proof images (Checklist ID: $checklist_id)");
        respond_json(true, 'All images removed successfully!');

    } catch (Exception $e) {
        $conn->rollback();
        respond_json(false, 'Server Error: ' . $e->getMessage());
    }
}

// ====================================================================
// ✅ HANDLE GET IMAGES ACTION (fetch all images for a checklist item)
// ====================================================================

if ($action === 'get_images') {
    $stmt = $conn->prepare("SELECT id, image_path, uploaded_at FROM checklist_images WHERE checklist_id = ? ORDER BY uploaded_at ASC");
    if (!$stmt) {
        respond_json(false, 'Database error.');
    }
    $stmt->bind_param("i", $checklist_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    respond_json(true, 'Images retrieved.', $images);
}

// ====================================================================
// ✅ HANDLE UPLOAD ACTION (add new image - no size limit in PHP)
// ====================================================================

$project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
$unit_id = filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT);

if (!$project_id || !$unit_id) {
    respond_json(false, 'Missing project/unit ID parameters for upload.');
}

if (!isset($_FILES['proof_file']) || $_FILES['proof_file']['error'] === UPLOAD_ERR_NO_FILE) {
    respond_json(false, 'No file was uploaded.');
}

// Process File Upload using the helper function
list($success, $result) = save_report_image($_FILES['proof_file'], $upload_dir);

if (!$success) {
    respond_json(false, "File upload failed: " . $result);
}

$file_name = $result;

// Insert into the checklist_images table (for multiple images support)
$stmt = $conn->prepare("INSERT INTO checklist_images (checklist_id, image_path) VALUES (?, ?)");

if (!$stmt) {
    error_log('DB prepare failed in process_upload_checklist_image: ' . $conn->error);
    respond_json(false, 'Database configuration error.');
}

$stmt->bind_param("is", $checklist_id, $file_name);

if ($stmt->execute()) {
    $new_image_id = $conn->insert_id;
    log_activity($conn, 'UPLOAD_CHECKLIST_IMAGE', "Uploaded checklist proof image (Image ID: $new_image_id, Checklist ID: $checklist_id)");
    respond_json(true, 'Image uploaded successfully!', ['image_id' => $new_image_id, 'image_path' => $file_name]);
} else {
    error_log('DB execute failed: ' . $stmt->error);
    respond_json(false, 'Failed to save image record.');
}

$stmt->close();
$conn->close();
?>