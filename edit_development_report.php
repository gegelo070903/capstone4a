<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

// Set timezone for date/time functions
date_default_timezone_set('Asia/Manila');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$report_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$report = null;
$error_message = '';
$status_message = ''; // This will be used for displaying errors on *this* page if POST fails

// --- Fetch the existing report details for initial load or for POST processing fallback ---
if ($report_id > 0) {
    // MODIFIED: Removed materials_left from SELECT, as it's being deprecated/removed from focus
    $stmt_select = $conn->prepare("SELECT cr.id, cr.project_id, cr.report_date, cr.start_time, cr.end_time,
                                          cr.status, cr.description, cr.proof_image, cr.constructor_id,
                                          p.constructor_id AS project_constructor_id, p.name AS project_name
                                   FROM construction_reports cr
                                   JOIN projects p ON cr.project_id = p.id
                                   WHERE cr.id = ?");
    $stmt_select->bind_param("i", $report_id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    $report = $result->fetch_assoc();
    $stmt_select->close();

    if (!$report) {
        $error_message = "Report not found.";
    }
} else {
    $error_message = "Invalid report ID provided.";
}

// --- If report not found or invalid ID, display error and EXIT immediately ---
if (!$report && !empty($error_message)) {
    include 'includes/header.php'; // Include header before any HTML output
    echo '<div class="main-content-wrapper">';
    echo '<div class="alert error">' . htmlspecialchars($error_message) . '</div>';
    echo '<p class="text-center"><a href="javascript:history.back()" class="btn btn-secondary">Go Back</a></p>';
    echo '</div>';
    include 'includes/footer.php';
    exit(); // IMPORTANT: Exit here to prevent further script execution
}

// --- Authorization Check (only if report was found) ---
// This block should run after fetching the report to get project_constructor_id
if ($report) {
    if (!is_admin() && $_SESSION['user_id'] != $report['project_constructor_id']) {
        header("Location: view_project.php?id=" . $report['project_id'] . "&status=report_updated_error&message=" . urlencode("Unauthorized to edit this report."));
        exit();
    }
}


// --- Handle form submission for update (only if report was found) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $report) { // Ensure $report exists
    $report_id_from_post = intval($_POST['report_id']); // Get ID from hidden field

    // Double check that the ID from POST matches the ID we loaded
    if ($report_id_from_post !== $report_id) {
        header("Location: view_project.php?id=" . $report['project_id'] . "&status=report_updated_error&message=" . urlencode("Security error: Report ID mismatch."));
        exit();
    }

    $project_id_for_redirect = $report['project_id']; // For redirection later

    $report_date = $_POST['report_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $status = $_POST['status'];
    $description = trim($_POST['description']);
    // REMOVED: $materials_left from here

    $old_proof_image = $report['proof_image']; // Keep track of the old image for deletion
    $proof_image_path = $old_proof_image; // Default to old image path

    $upload_dir = 'uploads/reports_proofs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Handle new image upload
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['proof_image']['tmp_name'];
        $file_name = uniqid() . '_' . basename($_FILES['proof_image']['name']);
        $new_file_path = $upload_dir . $file_name;

        if (move_uploaded_file($file_tmp_name, $new_file_path)) {
            $proof_image_path = $new_file_path;
            // Optionally, delete the old image if a new one is uploaded and it's not the default
            if (!empty($old_proof_image) && file_exists($old_proof_image)) {
                unlink($old_proof_image);
            }
        } else {
            $status_message = '<div class="alert error">Failed to upload new image.</div>';
            // If image upload fails, don't proceed with DB update if image is crucial
            // For now, we'll let it proceed with the old image path or NULL
        }
    }

    // Validate essential fields
    if (empty($report_date) || empty($start_time) || empty($end_time) || empty($status) || empty($description)) {
        $status_message = '<div class="alert error">All fields are required and must have valid values.</div>';
    } else {
        // MODIFIED: Removed materials_left from the UPDATE statement
        $stmt_update = $conn->prepare("UPDATE construction_reports SET
                                        report_date = ?, start_time = ?, end_time = ?,
                                        status = ?, description = ?, proof_image = ?
                                        WHERE id = ? AND project_id = ?");
        // CORRECTED: bind_param types to match new query (6 strings, 2 integers)
        $stmt_update->bind_param("ssssssii",
                                    $report_date, $start_time, $end_time,
                                    $status, $description,
                                    $proof_image_path, $report_id, $project_id_for_redirect);

        if ($stmt_update->execute()) {
            header("Location: view_project.php?id=" . $project_id_for_redirect . "&status=report_updated_success");
            exit();
        } else {
            $status_message = '<div class="alert error">Error updating report: ' . $stmt_update->error . '</div>';
        }
        $stmt_update->close();
    }
}

// Include header for the page (only if not exited earlier)
include 'includes/header.php';
?>

<!-- ========================================================= -->
<!-- CSS for edit_development_report.php                       -->
<!-- Reuses styles from other forms for consistency and modern look -->
<!-- ========================================================= -->
<style>
/* Reusing general styles from view_project.php to maintain consistency */
.main-content-wrapper {
    padding: 30px;
    max-width: 800px; /* Adjusted max-width for the form */
    margin: 30px auto; /* Centered with top/bottom margin */
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
}

.form-container {
    padding: 20px;
}

.form-header {
    display: flex;
    justify-content: flex-start; /* Aligned left as there's no secondary button here */
    align-items: center;
    margin-bottom: 25px;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
}

.form-header h2 {
    margin: 0;
    font-size: 2em;
    color: #333;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #555;
    font-size: 1.05em;
}

input[type="text"],
input[type="number"],
input[type="date"],
input[type="time"],
textarea,
select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1em;
    box-sizing: border-box;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

input[type="text"]:focus,
input[type="number"]:focus,
input[type="date"]:focus,
input[type="time"]:focus,
textarea:focus,
select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
    outline: none;
}

/* Style for read-only inputs */
input[readonly] {
    background-color: #e9ecef; /* Lighter background for non-editable fields */
    color: #6c757d;
    cursor: not-allowed;
}

textarea {
    min-height: 120px;
    resize: vertical;
    line-height: 1.5;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.form-row .form-group {
    flex: 1;
    min-width: 250px;
    margin-bottom: 0;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
}

.btn {
    padding: 10px 25px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    transition: background-color 0.3s ease, transform 0.2s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}
.btn:hover { transform: translateY(-2px); }
.btn-primary { background-color: #007bff; color: white; }
.btn-primary:hover { background-color: #0056b3; }
.btn-secondary { background-color: #6c757d; color: white; }
.btn-secondary:hover { background-color: #5a6268; }

/* Existing alert styles */
.alert {
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-weight: bold;
    text-align: center;
    font-size: 0.9em;
    opacity: 1;
    transition: opacity 0.5s ease-out;
}
.alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6fb; }
.alert.fade-out { opacity: 0; }

/* Warning alert style - kept for consistency but not used by this file now */
.alert.warning {
    background-color: #fff3cd; /* Light yellow background */
    color: #856404; /* Dark yellow text */
    border: 1px solid #ffeeba; /* Yellow border */
}

/* NEW: CSS for image preview to minimize it */
.current-image-preview {
    margin-top: 15px;
    border: 1px solid #e0e0e0;
    padding: 10px;
    border-radius: 8px;
    background-color: #f8f8f8;
    text-align: center;
}

.current-image-preview img {
    max-width: 100%; /* Ensure image doesn't overflow its container */
    max-height: 250px; /* Limit the maximum height of the image */
    height: auto; /* Maintain aspect ratio */
    display: block; /* Remove extra space below image */
    margin: 0 auto 10px auto; /* Center image and add some space below */
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.current-image-preview span {
    display: block;
    font-size: 0.9em;
    color: #666;
    font-style: italic;
}

.text-muted {
    font-size: 0.9em;
    color: #6c757d;
    margin-top: 5px;
}

</style>

<div class="main-content-wrapper">
    <div class="form-container">
        <?php
        // Display status message from current request on this page (e.g., validation error)
        if (!empty($status_message)) {
            echo $status_message;
        }
        ?>

        <div class="form-header">
            <h2>Edit Daily Report for Project: <?= htmlspecialchars($report['project_name'] ?? 'N/A'); ?></h2>
        </div>

        <?php if ($report): // Only show the form if a report was successfully loaded ?>
        <form action="edit_development_report.php?id=<?= $report_id ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="project_id" value="<?= $report['project_id']; ?>">
            <input type="hidden" name="report_id" value="<?= $report_id; ?>">

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
                <label for="status">Project Status for Today:</label>
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

            <!-- REMOVED materials_left input field -->

            <div class="form-group">
                <label for="proof_image">Upload New Proof Image (Optional):</label>
                <input type="file" id="proof_image" name="proof_image" accept="image/jpeg, image/png, image/gif">
                <?php if (!empty($report['proof_image'])): ?>
                    <div class="current-image-preview">
                        <img src="<?= htmlspecialchars($report['proof_image']) ?>" alt="Current Proof Image">
                        <span>Current Image</span>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No image currently uploaded.</p>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Report</button>
                <a href="view_project.php?id=<?= $report['project_id'] ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
        <?php else: // If report was not found on initial load and was not a POST attempt ?>
            <!-- This section is now handled by the initial check at the top -->
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    // JavaScript to make alert messages disappear after a few seconds (reused from other pages)
    document.addEventListener('DOMContentLoaded', () => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.classList.add('fade-out'); // Add fade-out class
                // After transition, remove element from DOM
                alert.addEventListener('transitionend', () => {
                    alert.remove();
                });
            }, 5000); // 5000 milliseconds = 5 seconds
        });
    });
</script>