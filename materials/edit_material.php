<?php
session_start(); // Ensure session is started for auth check
include 'includes/db.php';
include 'includes/functions.php';

date_default_timezone_set('Asia/Manila');

// Ensure only admin can access this page
if (!is_admin()) {
    header("Location: dashboard.php"); // Redirect non-admins
    exit();
}

$material_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$material = null;
$error_message = '';
$status_message = ''; // This will be used for displaying errors on *this* page if POST fails

// --- Fetch the existing material details for initial load or for POST processing fallback ---
if ($material_id > 0) {
    $stmt_select = $conn->prepare("SELECT m.*, p.name AS project_name
                                   FROM materials m
                                   LEFT JOIN projects p ON m.project_id = p.id
                                   WHERE m.id = ?");
    $stmt_select->bind_param("i", $material_id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    $material = $result->fetch_assoc();
    $stmt_select->close();

    if (!$material) {
        $error_message = "Material not found.";
    }
} else {
    $error_message = "Invalid material ID provided.";
}

// --- If material not found or invalid ID, display error and EXIT immediately ---
if (!$material && !empty($error_message)) {
    include 'includes/header.php'; // Include header before any HTML output
    echo '<div class="main-content-wrapper">';
    echo '<div class="alert error">' . htmlspecialchars($error_message) . '</div>';
    echo '<p class="text-center"><a href="supply_monitoring.php" class="btn btn-secondary">Back to Supply Monitoring</a></p>';
    echo '</div>';
    include 'includes/footer.php';
    exit(); // IMPORTANT: Exit here to prevent further script execution
}

// --- Handle form submission for update (only if material was found) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $material) { // Ensure $material exists
    $material_id_from_post = intval($_POST['material_id']); // Get ID from hidden field

    // Double check that the ID from POST matches the ID we loaded
    if ($material_id_from_post !== $material_id) {
        header("Location: supply_monitoring.php?status=material_updated_error&message=" . urlencode("Security error: Material ID mismatch."));
        exit();
    }

    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $supplier = trim($_POST['supplier']); // CORRECTED TYPO HERE
    $purpose = trim($_POST['purpose']);
    $quantity = intval($_POST['quantity']);
    $unit_of_measurement = trim($_POST['unit_of_measurement']);
    $total_amount = $price * $quantity;

    // Validate essential fields
    if (empty($name) || $price <= 0 || empty($supplier) || empty($purpose) || $quantity <= 0 || empty($unit_of_measurement)) {
        $status_message = '<div class="alert error">All fields are required and must have valid values.</div>';
    } else {
        $stmt_update = $conn->prepare("UPDATE materials SET name=?, quantity=?, unit_of_measurement=?, price=?, total_amount=?, supplier=?, purpose=? WHERE id=?");
        $stmt_update->bind_param("sisddssi", $name, $quantity, $unit_of_measurement, $price, $total_amount, $supplier, $purpose, $material_id);

        if ($stmt_update->execute()) {
            header("Location: supply_monitoring.php?status=material_updated_success");
            exit();
        } else {
            $status_message = '<div class="alert error">Error updating material: ' . $stmt_update->error . '</div>';
        }
        $stmt_update->close();
    }
}

// Include header for the page (only if not exited earlier)
include 'includes/header.php';
?>

<!-- ========================================================= -->
<!-- CSS for edit_material.php                                 -->
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
            <h2>Edit Material: <?= htmlspecialchars($material['name'] ?? 'N/A'); ?></h2>
        </div>

        <form action="edit_material.php?id=<?= $material_id ?>" method="POST">
            <input type="hidden" name="material_id" value="<?= $material_id; ?>">

            <div class="form-group">
                <label for="name">Material Name:</label>
                <input name="name" id="name" type="text" value="<?= htmlspecialchars($material['name']) ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="quantity">Quantity:</label>
                    <input name="quantity" id="quantity" type="number" min="1" step="0.01" value="<?= htmlspecialchars($material['quantity']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="unit_of_measurement">Unit of Measurement:</label>
                    <input name="unit_of_measurement" id="unit_of_measurement" type="text" value="<?= htmlspecialchars($material['unit_of_measurement']) ?>" placeholder="e.g., sacks, pcs, meters" required>
                </div>
            </div>

            <div class="form-group">
                <label for="price">Unit Price (₱):</label>
                <input name="price" id="price" type="number" step="0.01" min="0.01" value="<?= htmlspecialchars($material['price']) ?>" required>
            </div>

            <div class="form-group">
                <label for="supplier">Supplier:</label>
                <input name="supplier" id="supplier" type="text" value="<?= htmlspecialchars($material['supplier']) ?>" required>
            </div>

            <div class="form-group">
                <label for="purpose">Purpose:</label>
                <textarea name="purpose" id="purpose" rows="3" required><?= htmlspecialchars($material['purpose']) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="date_added">Date Added:</label>
                    <input name="date_added" id="date_added" type="date" value="<?= htmlspecialchars($material['date']) ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="time_added">Time Added:</label>
                    <input name="time_added" id="time_added" type="time" value="<?= htmlspecialchars($material['time']) ?>" readonly>
                </div>
            </div>

            <div class="form-group">
                <label for="assigned_project">Assigned Project:</label>
                <input name="assigned_project" id="assigned_project" type="text"
                       value="<?= htmlspecialchars($material['project_name'] ?? 'Unassigned') ?>" readonly>
            </div>


            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Material</button>
                <a href="supply_monitoring.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
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