<?php
// ===============================================================
// edit_development_report.php
// Allows authorized users (admin or assigned constructor)
// to edit an existing development report.
// ===============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../auth/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_login();

date_default_timezone_set('Asia/Manila');

// ---------------------------------------------------------------
// Validate report ID
// ---------------------------------------------------------------
$report_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($report_id <= 0) {
    redirect_with_message('../modules/development_monitoring.php', 'Invalid report ID.');
}

// Fetch report details
$stmt = $conn->prepare("
    SELECT cr.*, p.name AS project_name, p.assigned_to AS project_constructor_id
    FROM construction_reports cr
    JOIN projects p ON cr.project_id = p.id
    WHERE cr.id = ?
");
$stmt->bind_param('i', $report_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();
$stmt->close();

if (!$report) {
    redirect_with_message('../modules/development_monitoring.php', 'Report not found.');
}

// Authorization
if (!is_admin() && ($_SESSION['user_id'] ?? 0) !== (int)$report['project_constructor_id']) {
    redirect_with_message('../modules/view_project.php?id=' . $report['project_id'], 'Unauthorized to edit this report.');
}

// ---------------------------------------------------------------
// Handle update submission
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_id_from_post = intval($_POST['report_id']);
    if ($report_id_from_post !== $report_id) {
        redirect_with_message('../modules/view_project.php?id=' . $report['project_id'], 'Security error: Report ID mismatch.');
    }

    $project_id = $report['project_id'];
    $report_date = $_POST['report_date'] ?? '';
    $start_time  = $_POST['start_time'] ?? '';
    $end_time    = $_POST['end_time'] ?? '';
    $status      = $_POST['status'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if (!$report_date || !$start_time || !$end_time || !$status || !$description) {
        redirect_with_message('../modules/view_project.php?id=' . $project_id, 'All fields are required.');
    }

    $proof_image_path = $report['proof_image'];

    // Handle image upload
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed)) {
            redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Invalid file type.');
        }

        $upload_dir = __DIR__ . '/../uploads/reports_proofs/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $new_file = $upload_dir . uniqid('proof_', true) . '.' . $ext;
        if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $new_file)) {
            // Delete old image if exists
            if (!empty($proof_image_path) && file_exists(__DIR__ . '/../' . $proof_image_path)) {
                unlink(__DIR__ . '/../' . $proof_image_path);
            }
            $proof_image_path = 'uploads/reports_proofs/' . basename($new_file);
        } else {
            redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Failed to upload new proof image.');
        }
    }

    // Update record
    $stmt = $conn->prepare("
        UPDATE construction_reports
        SET report_date = ?, start_time = ?, end_time = ?, status = ?, description = ?, proof_image = ?
        WHERE id = ? AND project_id = ?
    ");
    $stmt->bind_param(
        'ssssssii',
        $report_date,
        $start_time,
        $end_time,
        $status,
        $description,
        $proof_image_path,
        $report_id,
        $project_id
    );

    if ($stmt->execute()) {
        redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Development report updated successfully!');
    } else {
        redirect_with_message('../modules/view_project.php?id=' . $project_id, 'Error updating report. Please try again.');
    }
    $stmt->close();
}

// ---------------------------------------------------------------
// Page HTML Rendering
// ---------------------------------------------------------------
include '../includes/header.php';
?>

<!-- ========================================================= -->
<!-- CSS STYLES (kept original look) -->
<!-- ========================================================= -->
<style>
.main-content-wrapper {
    padding: 30px;
    max-width: 800px;
    margin: 30px auto;
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
}
.form-container { padding: 20px; }
.form-header {
    display: flex;
    align-items: center;
    margin-bottom: 25px;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
}
.form-header h2 { margin: 0; font-size: 2em; color: #333; }
.form-group { margin-bottom: 20px; }
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #555;
    font-size: 1.05em;
}
input[type="text"], input[type="date"], input[type="time"], textarea, select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1em;
    box-sizing: border-box;
}
textarea { min-height: 120px; resize: vertical; line-height: 1.5; }
.form-row { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
.form-row .form-group { flex: 1; min-width: 250px; margin-bottom: 0; }
.form-actions { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; }
.btn {
    padding: 10px 25px; border-radius: 8px; text-decoration: none;
    font-weight: bold; border: none; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; gap: 5px;
}
.btn-primary { background-color: #007bff; color: white; }
.btn-primary:hover { background-color: #0056b3; }
.btn-secondary { background-color: #6c757d; color: white; }
.btn-secondary:hover { background-color: #5a6268; }
.current-image-preview {
    margin-top: 15px; border: 1px solid #e0e0e0; padding: 10px;
    border-radius: 8px; background-color: #f8f8f8; text-align: center;
}
.current-image-preview img {
    max-width: 100%; max-height: 250px; height: auto; display: block;
    margin: 0 auto 10px auto; border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
</style>

<div class="main-content-wrapper">
    <div class="form-container">
        <div class="form-header">
            <h2>Edit Development Report for: <?= htmlspecialchars($report['project_name']); ?></h2>
        </div>

        <form action="edit_development_report.php?id=<?= $report_id ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="report_id" value="<?= $report_id ?>">
            <input type="hidden" name="project_id" value="<?= $report['project_id'] ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="report_date">Report Date:</label>
                    <input type="date" id="report_date" name="report_date" value="<?= htmlspecialchars($report['report_date']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="start_time">Start Time:</label>
                    <input type="time" id="start_time" name="start_time" value="<?= htmlspecialchars($report['start_time']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="end_time">End Time:</label>
                    <input type="time" id="end_time" name="end_time" value="<?= htmlspecialchars($report['end_time']); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="status">Status:</label>
                <select id="status" name="status" required>
                    <option value="ongoing" <?= ($report['status'] == 'ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="complete" <?= ($report['status'] == 'complete') ? 'selected' : ''; ?>>Complete</option>
                    <option value="pending" <?= ($report['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description of Work:</label>
                <textarea id="description" name="description" rows="5" required><?= htmlspecialchars($report['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="proof_image">Upload New Proof Image (Optional):</label>
                <input type="file" id="proof_image" name="proof_image" accept="image/jpeg, image/png, image/webp">
                <?php if (!empty($report['proof_image'])): ?>
                    <div class="current-image-preview">
                        <img src="../<?= htmlspecialchars($report['proof_image']); ?>" alt="Current Proof Image">
                        <span>Current Image</span>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No image currently uploaded.</p>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Report</button>
                <a href="../modules/view_project.php?id=<?= $report['project_id'] ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>