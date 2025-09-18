<?php
// supply_monitoring.php

// --- ALL PHP LOGIC MUST COME BEFORE ANY HTML OUTPUT ---

session_start(); // Ensure session is started for auth check
include 'includes/db.php';
include 'includes/functions.php'; // For is_admin() and session checks

date_default_timezone_set('Asia/Manila');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// --- PHP Logic for Search/Filter ---
$search_query = "";
$search_clause = "";
$search_params = [];

if (isset($_GET['query']) && !empty(trim($_GET['query']))) {
    $search_query = trim($_GET['query']);
    // Search materials by name, supplier, or the linked project name
    $search_clause = " WHERE m.name LIKE ? OR m.supplier LIKE ? OR p.name LIKE ?";
    $search_params = ["%$search_query%", "%$search_query%", "%$search_query%"];
}

// --- PHP Logic for Sorting ---
$sort_column = "m.date, m.time"; // Default sort: by date and time
$sort_order = "DESC"; // Default sort order: newest first

if (isset($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'name_asc':
            $sort_column = "m.name";
            $sort_order = "ASC";
            break;
        case 'name_desc':
            $sort_column = "m.name";
            $sort_order = "DESC";
            break;
        case 'qty_asc':
            $sort_column = "m.quantity";
            $sort_order = "ASC";
            break;
        case 'qty_desc':
            $sort_column = "m.quantity";
            $sort_order = "DESC";
            break;
        case 'price_asc':
            $sort_column = "m.price";
            $sort_order = "ASC";
            break;
        case 'price_desc':
            $sort_column = "m.price";
            $sort_order = "DESC";
            break;
        case 'oldest':
            $sort_column = "m.date, m.time";
            $sort_order = "ASC";
            break;
        case 'newest': // Default, handled by default $sort_order
        default:
            $sort_column = "m.date, m.time";
            $sort_order = "DESC";
            break;
    }
}


// --- Dynamic SQL Query based on User Role ---
$sql = "SELECT
            m.*,
            m.unit_of_measurement,
            p.name AS project_name,
            u.username AS constructor_name,
            latest_deduction.deducted_at AS last_deducted_at,
            deductor_user.username AS last_deducted_by
        FROM materials m
        LEFT JOIN projects p ON m.project_id = p.id
        LEFT JOIN users u ON p.constructor_id = u.id
        LEFT JOIN (
            SELECT
                material_id,
                MAX(deducted_at) AS deducted_at,
                deducted_by_user_id
            FROM report_material_usage
            WHERE is_deducted = TRUE
            GROUP BY material_id
        ) AS latest_deduction ON m.id = latest_deduction.material_id
        LEFT JOIN users AS deductor_user ON latest_deduction.deducted_by_user_id = deductor_user.id";


$param_types = "";
$param_values = [];

// Determine WHERE clause and parameters based on role and search
if (is_admin()) {
    if (!empty($search_clause)) {
        $sql .= $search_clause;
        $param_types = str_repeat('s', count($search_params));
        $param_values = $search_params;
    }
} else {
    $current_user_id = $_SESSION['user_id'];
    $constructor_filter_clause = " (m.project_id IS NULL OR p.constructor_id = ?) ";

    if (!empty($search_clause)) {
        $sql .= " WHERE " . $constructor_filter_clause . " AND (m.name LIKE ? OR m.supplier LIKE ? OR p.name LIKE ?)";
        $param_types = "isss";
        $param_values = [$current_user_id, "%$search_query%", "%$search_query%", "%$search_query%"];
    } else {
        $sql .= " WHERE " . $constructor_filter_clause;
        $param_types = "i";
        $param_values = [$current_user_id];
    }
}

$sql .= " ORDER BY {$sort_column} {$sort_order}";

$stmt = $conn->prepare($sql);

if (!empty($param_values)) {
    // Dynamically bind parameters based on the param_types string and param_values array
    $stmt->bind_param($param_types, ...$param_values);
}
$stmt->execute();
$materials_result = $stmt->get_result();

// --- Handle Status Messages from GET and SESSION (for immediate display) ---
$status_message = '';
// Prioritize GET messages (e.g., from direct redirects)
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'material_added_success') {
        $status_message = '<div class="alert success">Material added successfully!</div>';
    } elseif ($_GET['status'] === 'material_added_error') {
        $error_details = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error adding material. Please try again.';
        $status_message = '<div class="alert error">' . $error_details . '</div>';
    } elseif ($_GET['status'] === 'material_updated_success') {
        $status_message = '<div class="alert success">Material updated successfully!</div>'; // Back to green
    } elseif ($_GET['status'] === 'material_updated_error') {
        $error_details = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error updating material. Please try again.';
        $status_message = '<div class="alert error">' . $error_details . '</div>';
    } elseif ($_GET['status'] === 'material_deleted_success') {
        $status_message = '<div class="alert success">Material deleted successfully!</div>';
    } elseif ($_GET['status'] === 'material_deleted_error') {
        $error_details = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error deleting material. Please try again.';
        $status_message = '<div class="alert error">' . $error_details . '</div>';
    }
    // New status for material deductions (from process_deduct_material_usage.php or view_project.php redirects)
    elseif ($_GET['status'] === 'material_deducted_success') {
        $status_message = '<div class="alert success">Material quantity deducted successfully!</div>';
    } elseif ($_GET['status'] === 'material_deducted_error') {
        $error_details = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Error deducting material quantity. Please try again.';
        $status_message = '<div class="alert error">' . $error_details . '</div>';
    }
}
// Fallback to SESSION message if no GET status (e.g., if a previous page used session directly)
if (empty($status_message) && isset($_SESSION['status_message'])) {
    $status_message = $_SESSION['status_message'];
    unset($_SESSION['status_message']); // Clear message after displaying
}


// --- START OF HTML OUTPUT ---
include 'includes/header.php'; // This includes the opening <body> and other header elements
?>

<!-- ========================================================= -->
<!-- CSS for supply_monitoring.php (all CSS for this page)     -->
<!-- ========================================================= -->
<style>
/* Main Content Wrapper (assuming it's used consistently across dashboard/projects) */
.main-content-wrapper {
    padding: 30px;
    max-width: 1200px;
    margin: 0 auto;
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
}

/* Header Section (assuming it's used consistently) */
.header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.section-title {
    font-size: 2em;
    color: #333;
    margin: 0;
}

/* Generic Button Styles (if not defined globally elsewhere) */
.btn {
    padding: 10px 20px;
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
.btn:hover {
    transform: translateY(-2px);
}
.btn-primary {
    background-color: #007bff;
    color: white;
}
.btn-primary:hover {
    background-color: #0056b3;
}

/* Alert messages (if not defined globally) */
.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: bold;
    text-align: center;
    opacity: 1; /* Start fully visible */
    transition: opacity 0.5s ease-out; /* Smooth fade-out transition */
}
.alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6fb; }
.alert.fade-out {
    opacity: 0;
}

/* No data message (if not defined globally) */
.no-data-message {
    text-align: center;
    color: #777;
    font-style: italic;
    padding: 20px;
}

/* ========================================================= */
/* Specific Styles for Supply Monitoring Page                */
/* ========================================================= */

/* Control Bar above the table */
.materials-control-bar {
    display: flex;
    justify-content: space-between; /* Pushes search to left, actions to right */
    align-items: center;
    flex-wrap: wrap; /* Allow wrapping on smaller screens */
    gap: 15px; /* Space between elements */
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    padding: 15px 25px;
    margin-bottom: 25px; /* Space below the control bar */
}

.search-bar-materials {
    display: flex;
    align-items: center;
    border: 1px solid #ddd;
    border-radius: 24px;
    padding: 5px 8px;
    width: 350px; /* Fixed width for consistency */
    max-width: 100%;
    background-color: #f8f9fa;
    flex-shrink: 0; /* Prevent from shrinking on small screens */
}

.search-bar-materials input {
    border: none;
    outline: none;
    width: 100%;
    padding: 5px;
    font-size: 14px;
    background-color: transparent;
}

.search-bar-materials button {
    background-color: #007bff;
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 14px;
    flex-shrink: 0;
}
.search-bar-materials button:hover {
    background-color: #0056b3;
}

.control-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.sort-dropdown label {
    margin-right: 8px;
    font-weight: 500;
    color: #555;
}

.sort-dropdown select {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 0.9em;
    cursor: pointer;
    background-color: #fff;
    min-width: 140px; /* Adjust dropdown width */
}

/* ========================================================= */
/* Table Container for Fixed Header & Scrollable Body        */
/* ========================================================= */
.materials-table-container {
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    max-height: calc(100vh - 250px); /* Adjust based on your header/control bar height */
    overflow-y: auto; /* ONLY vertical scrolling for the table body */
    overflow-x: auto; /* Allow horizontal scroll for the table itself if it overflows */
    padding: 20px; /* Internal padding for the card */
}

.sticky-header-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed; /* Crucial for fixed column widths */
    /* REMOVED min-width: 1050px; to allow it to shrink dynamically */
}

.sticky-header-table thead {
    position: sticky; /* Makes the header sticky */
    top: 0;           /* Sticks to the top of its scrollable parent */
    z-index: 5;       /* Ensures it's above scrolling tbody content */
    background-color: #f8f9fa; /* Background to prevent content showing through */
}

.sticky-header-table th,
.sticky-header-table td {
    padding: 8px 5px; /* Reduced horizontal padding for compactness */
    vertical-align: top; /* Align content to the top */
    white-space: normal;
    word-wrap: break-word;
    font-size: 0.85em; /* Slightly smaller base font size for table cells */
    border-bottom: 1px solid #dee2e6;
    line-height: 1.2; /* Tighter line height for readability */
}

.sticky-header-table thead th {
    background-color: #e9edf2;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #ced4da;
    text-align: left;
    padding-top: 10px;
    padding-bottom: 10px;
}


/* Specific text alignment for numeric columns */
.text-right {
    text-align: right;
}
.text-center {
    text-align: center;
}

/* Optimized Column Widths (aiming for total ~100% within a narrower viewport) */
.sticky-header-table th:nth-child(1), /* ID */
.sticky-header-table td:nth-child(1) { width: 4%; text-align: center; }
.sticky-header-table th:nth-child(2), /* Name */
.sticky-header-table td:nth-child(2) { width: 13%; } /* Adjusted */
.sticky-header-table th:nth-child(3), /* Quantity */
.sticky-header-table td:nth-child(3) { width: 8%; text-align: right; }
.sticky-header-table th:nth-child(4), /* Unit Price */
.sticky-header-table td:nth-child(4) { width: 7%; text-align: right; }
.sticky-header-table th:nth-child(5), /* Total Value */
.sticky-header-table td:nth-child(5) { width: 9%; text-align: right; }
.sticky-header-table th:nth-child(6), /* Supplier */
.sticky-header-table td:nth-child(6) { width: 11%; }
.sticky-header-table th:nth-child(7), /* Purpose */
.sticky-header-table td:nth-child(7) { width: 12%; }
.sticky-header-table th:nth-child(8), /* Date Added */
.sticky-header-table td:nth-child(8) { width: 11%; } /* Adjusted */
.sticky-header-table th:nth-child(9), /* Last Deducted */
.sticky-header-table td:nth-child(9) { width: 11%; } /* Adjusted */
.sticky-header-table th:nth-child(10), /* Project (Assigned to) */
.sticky-header-table td:nth-child(10) { width: 9%; } /* Adjusted */
.sticky-header-table th:nth-child(11), /* Actions */
.sticky-header-table td:nth-child(11) {
    width: 8%; /* Adjusted to ensure buttons fit */
    text-align: center;
}


/* Styling for the simple text links in the actions column */
.action-link-edit {
    color: #007bff;
    text-decoration: none;
    font-weight: 500;
    margin-right: 5px; /* Space between Edit and Delete */
}
.action-link-edit:hover {
    text-decoration: underline;
}
.action-link-delete {
    color: #dc3545;
    text-decoration: none;
    font-weight: 500;
}
.action-link-delete:hover {
    text-decoration: underline;
}

/* Badge for 'Unassigned' project */
.badge {
    padding: 3px 6px; /* More compact badge */
    border-radius: 10px; /* Slightly less rounded */
    font-size: 0.7em; /* Smaller font for badge */
    font-weight: bold;
    color: white;
    white-space: nowrap;
}

.badge.unassigned {
    background-color: #6c757d;
}

/* Styles for last deducted info */
.last-deducted-info {
    font-size: 0.7em; /* Smaller font for compactness */
    color: #555;
    line-height: 1.1; /* Tighter line height */
    text-align: left; /* Align text within cell */
}
.last-deducted-info strong {
    color: #333;
    display: block; /* Make date bold and on its own line */
    font-size: 1em; /* Adjusted date font size */
}
.last-deducted-info .date-time {
    display: block;
    color: #777;
    font-size: 0.85em; /* Adjusted */
    margin-top: 1px;
}
.last-deducted-info .user {
    font-style: italic;
    color: #777;
    display: block;
    margin-top: 1px;
}

/* Styling for quantity and unit */
.qty-unit-display {
    display: flex;
    flex-direction: column; /* Stack quantity and unit */
    align-items: flex-end; /* Align to the right, as header is text-right */
}
.qty-unit-display .quantity-value {
    font-weight: 600;
    color: #333;
}
.qty-unit-display .unit-value {
    font-size: 0.7em; /* Smaller unit text */
    color: #777;
    margin-top: -2px; /* Pull unit slightly closer to quantity */
    white-space: nowrap; /* Prevent unit from wrapping too aggressively */
}


/* Adjust project link display for better wrapping */
td a {
    display: block;
    word-break: break-word;
    text-decoration: none;
    color: #007bff;
}
td a:hover {
    text-decoration: underline;
}
td .constructor-name {
    font-size: 0.75em; /* Smaller constructor name */
    color: #555;
    display: block;
    margin-top: 1px;
}

/* Actions cell button group */
.action-buttons-group {
    display: flex;
    justify-content: center; /* Center the buttons horizontally within the cell */
    gap: 4px; /* Smaller gap between buttons */
    flex-wrap: wrap; /* Allow buttons to wrap if column becomes very narrow */
}
.actions-cell a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-weight: bold;
    transition: background-color 0.2s ease, transform 0.1s ease;
    padding: 4px 6px; /* More compact padding */
    font-size: 0.75em; /* Smaller font size for buttons */
    border-radius: 4px; /* Slightly less rounded */
    min-width: 35px; /* Minimum width for buttons */
}
.actions-cell a:hover {
    transform: translateY(-1px);
}
/* Specific button colors */
.actions-cell .action-link-edit {
    background-color: #ffc107; /* Yellow */
    color: #333;
}
.actions-cell .action-link-edit:hover {
    background-color: #e0a800;
}
.actions-cell .action-link-delete {
    background-color: #dc3545; /* Red */
    color: white;
}
.actions-cell .action-link-delete:hover {
    background-color: #c82333;
}

</style>

<div class="main-content-wrapper">
    <!-- Display Status Messages here -->
    <?php echo $status_message; ?>

    <div class="materials-control-bar">
        <form action="supply_monitoring.php" method="GET" class="search-bar-materials">
            <input type="text" name="query" placeholder="Search material, supplier, project..." value="<?= htmlspecialchars($search_query); ?>">
            <button type="submit">🔍</button>
        </form>

        <div class="control-actions">
            <div class="sort-dropdown">
                <label for="sort-materials">Sort by:</label>
                <select id="sort-materials" onchange="window.location.href = 'supply_monitoring.php?sort=' + this.value + '<?php echo (!empty($search_query) ? '&query=' . urlencode($search_query) : ''); ?>';">
                    <option value="newest" <?php if (!isset($_GET['sort']) || $_GET['sort'] === 'newest') echo 'selected'; ?>>Date (Newest First)</option>
                    <option value="oldest" <?php if (isset($_GET['sort']) && $_GET['sort'] === 'oldest') echo 'selected'; ?>>Date (Oldest First)</option>
                    <option value="name_asc" <?php if (isset($_GET['sort']) && $_GET['sort'] === 'name_asc') echo 'selected'; ?>>Name (A-Z)</option>
                    <option value="name_desc" <?php if (isset($_GET['sort']) && $_GET['sort'] === 'name_desc') echo 'selected'; ?>>Name (Z-A)</option>
                    <option value="qty_asc" <?php if (isset($_GET['sort']) && $_GET['sort'] === 'qty_asc') echo 'selected'; ?>>Quantity (Low to High)</option>
                    <option value="qty_desc" <?php if (isset($_GET['sort']) && $_GET['sort'] === 'qty_desc') echo 'selected'; ?>>Quantity (High to Low)</option>
                    <option value="price_asc" <?php if (isset($_GET['sort']) && $_GET['sort'] === 'price_asc') echo 'selected'; ?>>Price (Low to High)</option>
                    <option value="price_desc" <?php if (isset($_GET['sort']) && $_GET['sort'] === 'price_desc') echo 'selected'; ?>>Price (High to Low)</option>
                </select>
            </div>
            <?php if (is_admin()): ?>
                <a href="add_material.php" class="btn btn-primary">Add New Material</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Table Container for Fixed Header & Scrollable Body -->
    <div class="materials-table-container">
        <?php if ($materials_result->num_rows > 0): ?>
            <table class="sticky-header-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total Value</th>
                        <th>Supplier</th>
                        <th>Purpose</th>
                        <th>Date Added</th>
                        <th>Last Deducted</th>
                        <th>Project (Assigned to)</th>
                        <?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while($material = $materials_result->fetch_assoc()):
                        // Safely format date, handling '0000-00-00'
                        $material_date_str = $material['date'];
                        $material_time_str = $material['time'];

                        $formatted_date_time = 'N/A';
                        if ($material_date_str !== '0000-00-00' && $material_date_str !== null) {
                            $material_date_timestamp = strtotime($material_date_str);
                            $formatted_date = date('M d, Y', $material_date_timestamp);
                            $formatted_time = ($material_time_str) ? date('h:i A', strtotime($material_time_str)) : '';
                            $formatted_date_time = $formatted_date;
                            // Add time only if it's a meaningful time (not '12:00 AM' which is often a default)
                            if (!empty($formatted_time) && $formatted_time !== '12:00 AM') {
                                $formatted_date_time .= ' (' . $formatted_time . ')';
                            }
                        }

                        // Format Last Deducted Info
                        $last_deducted_display = 'N/A';
                        if (!empty($material['last_deducted_at'])) {
                            $deducted_timestamp = strtotime($material['last_deducted_at']);
                            $deducted_date = date('m-d-Y', $deducted_timestamp); // Use MM-DD-YYYY
                            $deducted_time = date('h:i A', $deducted_timestamp);
                            $deducted_by = htmlspecialchars($material['last_deducted_by']);
                            $last_deducted_display = "<span class='last-deducted-info'><strong>{$deducted_date}</strong><span class='date-time'>{$deducted_time}</span><span class='user'>by {$deducted_by}</span></span>";
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($material['id']) ?></td>
                        <td><?= htmlspecialchars($material['name']) ?></td>
                        <td class="text-right">
                            <div class="qty-unit-display">
                                <span class="quantity-value"><?= htmlspecialchars(number_format($material['quantity'], 0)) ?></span>
                                <span class="unit-value"><?= htmlspecialchars($material['unit_of_measurement']) ?></span>
                            </div>
                        </td>
                        <td class="text-right">₱<?= htmlspecialchars(number_format($material['price'], 0)) ?></td>
                        <td class="text-right">₱<?= htmlspecialchars(number_format($material['total_amount'], 0)) ?></td>
                        <td><?= htmlspecialchars($material['supplier']) ?></td>
                        <td><?= htmlspecialchars($material['purpose']) ?></td>
                        <td><?= $formatted_date_time ?></td>
                        <td><?= $last_deducted_display ?></td>
                        <td>
                            <?php if ($material['project_id']): ?>
                                <a href="view_project.php?id=<?= htmlspecialchars($material['project_id']) ?>"><?= htmlspecialchars($material['project_name']) ?></a>
                                <span class="constructor-name">(<?= htmlspecialchars($material['constructor_name']) ?>)</span>
                            <?php else: ?>
                                <span class="badge unassigned">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <?php if (is_admin()): ?>
                        <td class="actions-cell">
                            <div class="action-buttons-group">
                                <a href="edit_material.php?id=<?= htmlspecialchars($material['id']) ?>" class="action-link-edit">Edit</a>
                                <a href="delete_material.php?id=<?= htmlspecialchars($material['id']) ?>" class="action-link-delete" onclick="return confirm('Are you sure you want to delete this material record?')">Delete</a>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data-message">No materials found in the system<?= (!empty($search_query) ? ' matching "' . htmlspecialchars($search_query) . '"' : '') ?>.</p>
        <?php endif; ?>
    </div>
</div>

</div> <!-- This closing div likely comes from your header.php file (for .main-container) -->

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
</body>
</html>