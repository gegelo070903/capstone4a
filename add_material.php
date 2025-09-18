<?php
// add_material.php

session_start(); // Ensure session is started for auth check
include 'includes/db.php';
include 'includes/functions.php';

// --- Security and Form Logic (BEFORE any HTML) ---
date_default_timezone_set('Asia/Manila');

if (!is_admin()) {
    header("Location: dashboard.php"); // Redirect non-admins to dashboard or login
    exit();
}

// Check if a project_id was passed in the URL. If not, we can't add a material.
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id === 0) {
    // Attempt to get project_id from POST if it's a form submission
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    if ($project_id === 0) {
        // If still no project ID, show error or redirect
        header("Location: projects.php?status=material_added_error&message=" . urlencode("Error: No project selected to add material."));
        exit();
    }
}

// Fetch project name for the form title
$project_name = "Unknown Project";
$stmt_project_name = $conn->prepare("SELECT name FROM projects WHERE id = ?");
$stmt_project_name->bind_param("i", $project_id);
$stmt_project_name->execute();
$project_name_result = $stmt_project_name->get_result();
if ($project_row = $project_name_result->fetch_assoc()) {
    $project_name = $project_row['name'];
}
$stmt_project_name->close();


$status_message = ''; // Initialize status message for display on *this* page

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id_from_form = intval($_POST['project_id']);

    // Basic validation and sanitization
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $supplier = trim($_POST['supplier']);
    $purpose = trim($_POST['purpose']);
    $quantity = intval($_POST['quantity']); // Ensure quantity is an integer
    $unit_of_measurement = trim($_POST['unit_of_measurement']);

    // Server-generated date and time
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');

    // Calculate total amount
    $total_amount = $price * $quantity;

    if (empty($name) || $price <= 0 || empty($supplier) || empty($purpose) || $quantity <= 0 || empty($unit_of_measurement)) {
        $status_message = '<div class="alert error">All fields are required and must have valid values.</div>';
    } else {
        // CORRECTED: bind_param types to match materials table schema and PHP variable types
        // materials (project_id, name, quantity, unit_of_measurement, price, total_amount, supplier, purpose, date, time)
        // Assuming quantity in DB is DECIMAL(10,2) but we want integer input, so it will store as X.00
        $stmt = $conn->prepare("INSERT INTO materials (project_id, name, quantity, unit_of_measurement, price, total_amount, supplier, purpose, date, time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isdsdsssss",
                            $project_id_from_form,
                            $name,
                            $quantity, // bound as integer, will convert to X.00 in DECIMAL field
                            $unit_of_measurement,
                            $price,
                            $total_amount,
                            $supplier,
                            $purpose,
                            $current_date,
                            $current_time);

        if ($stmt->execute()) {
            // CHANGED: Redirect with GET parameter for supply_monitoring.php to pick up
            header("Location: supply_monitoring.php?status=material_added_success");
            exit();
        } else {
            $status_message = '<div class="alert error">Error adding material: ' . htmlspecialchars($stmt->error) . '</div>';
        }
        $stmt->close();
    }
}

// Include header for the page
include 'includes/header.php';
?>

<style>
/* ABSOLUTE RESET for html/body to remove any default browser margins/padding */
html, body {
    margin: 0;
    padding: 0;
    height: 100%; /* Ensure body takes full viewport height */
    overflow: hidden; /* Hide body overflow globally to prevent unwanted scrollbars */
}

/* Reusing general styles from view_project.php to maintain consistency */
.main-content-wrapper {
    padding: 10px; /* Reduced overall padding for tighter fit */
    max-width: 950px; /* Adjusted width to fill space */
    margin: 10px auto; /* Reduced top/bottom margin */
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    height: auto; /* Allow content to define height */
    overflow-y: hidden; /* Prevent scrolling on this wrapper itself */
}

.form-container {
    padding: 10px 15px; /* Comfortable internal padding */
}

.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px; /* More comfortable margin */
    border-bottom: 1px solid #eee;
    padding-bottom: 8px; /* More comfortable padding */
}

.form-header h2 {
    margin: 0;
    font-size: 1.7em; /* Slightly adjusted font size */
    color: #333;
    line-height: 1.2;
}

.form-group {
    margin-bottom: 10px; /* Comfortable margin between form groups */
}

.form-group label {
    display: block;
    margin-bottom: 3px; /* Good space below label */
    font-weight: 600;
    color: #555;
    font-size: 0.9em; /* Slightly adjusted label font size */
}

input[type="text"],
input[type="number"], /* Quantity is now int type */
textarea,
select {
    width: 100%;
    padding: 7px 10px; /* Comfortable input padding */
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.88em; /* Slightly adjusted font size */
    box-sizing: border-box;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

input[type="text"]:focus,
input[type="number"]:focus,
textarea:focus,
select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2);
    outline: none;
}

textarea {
    min-height: 45px; /* Slightly more comfortable min-height */
    max-height: 90px; /* More flexible max height */
    resize: vertical; /* Allow vertical resizing, but constrained */
    line-height: 1.3;
}

.form-row {
    display: flex;
    gap: 15px; /* Good gap for horizontal separation */
    margin-bottom: 10px; /* Comfortable margin */
    flex-wrap: wrap; /* Allows wrapping on smaller screens */
}

.form-row .form-group {
    flex: 1;
    min-width: 250px; /* Maintain good minimum width for columns */
    margin-bottom: 0;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px; /* Comfortable gap between buttons */
    margin-top: 15px; /* Comfortable space above buttons */
}

.btn {
    padding: 8px 18px; /* Comfortable button padding */
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
    font-size: 0.95em; /* Comfortable button font size */
}
.btn:hover { transform: translateY(-1px); }
.btn-primary { background-color: #007bff; color: white; }
.btn-primary:hover { background-color: #0056b3; }
.btn-secondary { background-color: #6c757d; color: white; }
.btn-secondary:hover { background-color: #5a6268; }

.alert { /* Consistent alert styling */
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
</style>

<div class="main-content-wrapper">
    <div class="form-container">
        <?php
        // Display status message from current request on this page
        if (!empty($status_message)) {
            echo $status_message;
        }
        ?>

        <div class="form-header">
            <h2>Add New Material to Project: <?= htmlspecialchars($project_name); ?></h2>
        </div>

        <form action="add_material.php?project_id=<?= $project_id; ?>" method="POST">
            <input type="hidden" name="project_id" value="<?= $project_id; ?>">

            <div class="form-group">
                <label for="name">Material Name:</label>
                <input name="name" id="name" type="text" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="quantity">Quantity:</label>
                    <input name="quantity" id="quantity" type="number" min="1" required> <!-- Removed step="0.01" -->
                </div>
                <div class="form-group">
                    <label for="unit_of_measurement">Unit of Measurement:</label>
                    <input name="unit_of_measurement" id="unit_of_measurement" type="text" placeholder="e.g., sacks, pcs, meters" required>
                </div>
            </div>

            <div class="form-group">
                <label for="price">Unit Price:</label>
                <input name="price" id="price" type="number" step="0.01" min="0.01" required>
            </div>

            <div class="form-group">
                <label for="supplier">Supplier:</label>
                <input name="supplier" id="supplier" type="text" required>
            </div>

            <div class="form-group">
                <label for="purpose">Purpose / Usage:</label>
                <textarea name="purpose" id="purpose" rows="2" required></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Material</button>
                <a href="view_project.php?id=<?= $project_id ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    // JavaScript to make alert messages disappear after a few seconds
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