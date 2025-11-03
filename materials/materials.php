<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// Fetch all materials with linked project name
// MODIFIED: Removed 'm.supplier' from SELECT query
$query = "
    SELECT m.id, m.name, m.project_id, m.total_quantity, m.remaining_quantity, m.unit_of_measurement, m.purpose, m.created_at, p.name AS project_name 
    FROM materials m 
    LEFT JOIN projects p ON m.project_id = p.id 
    ORDER BY m.created_at DESC
";
$result = $conn->query($query);
$projects = $conn->query("SELECT id, name FROM projects ORDER BY name ASC"); // Fetch project list for the filter
?>

<!-- Page Container -->
<div class="content-container">
    <div class="page-header">
        <h2>Materials Inventory</h2>
        <!-- REMOVED: <a href="process_add_material.php" class="btn-primary"> ... </a> -->
    </div>

    <!-- START: Search & Filter Options -->
    <div class="filter-bar">
        <input type="text" id="searchInput" placeholder="Search material or supplier...">
        <select id="projectFilter">
            <option value="">All Projects</option>
            <?php
            if ($projects) {
                while ($p = $projects->fetch_assoc()) {
                    echo "<option value='{$p['name']}'>{$p['name']}</option>";
                }
            }
            ?>
        </select>
    </div>
    <!-- END: Search & Filter Options -->

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Material</th>
                        <!-- REMOVED: Supplier column header -->
                        <th>Project</th>
                        <th>Total Quantity</th>
                        <th>Remaining</th>
                        <th>Unit</th>
                        <th>Purpose</th>
                        <th>Date Added</th>
                        <!-- REMOVED: Actions column header -->
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        $counter = 1;
                        // Colspan reduced from 9 to 8 (removed Actions column)
                        $colspan = 8; 
                        
                        while ($row = $result->fetch_assoc()) {
                            // Convert to integers (remove decimals)
                            $total_qty = intval($row['total_quantity']);
                            $remaining_qty = intval($row['remaining_quantity']);

                            // Low Stock Indicator
                            $lowStock = ($total_qty > 0) && (($remaining_qty / $total_qty) < 0.2); 
                            $rowClass = $lowStock ? "low-stock" : "";

                            echo "<tr class='{$rowClass}'>
                                <td>{$counter}</td>
                                <td class='highlight' style='color:#2c3e50; font-weight:600;'>{$row['name']}</td>
                                <!-- REMOVED: Supplier column data -->
                                <td>{$row['project_name']}</td>
                                <td>{$total_qty}</td>
                                <td>{$remaining_qty}</td>
                                <td>{$row['unit_of_measurement']}</td>
                                <td>{$row['purpose']}</td>
                                <td>" . date('M d, Y', strtotime($row['created_at'])) . "</td>
                                <!-- REMOVED: Actions column data -->
                            </tr>";
                            $counter++;
                        }
                    } else {
                        // MODIFIED: colspan set to 8
                        echo "<tr><td colspan='8' class='empty'>No materials found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- JavaScript for Search and Filter -->
<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
    applyFilters();
});

document.getElementById("projectFilter").addEventListener("change", function() {
    applyFilters();
});

function applyFilters() {
    const textFilter = document.getElementById("searchInput").value.toLowerCase();
    const projectFilter = document.getElementById("projectFilter").value.toLowerCase();
    const rows = document.querySelectorAll(".data-table tbody tr");

    rows.forEach(row => {
        const rowText = row.textContent.toLowerCase();
        // The project name is now in cell index 2 
        // # (0), Material (1), Project (2), Total Quantity (3), Remaining (4), Unit (5), Purpose (6), Date Added (7)
        const projectCellText = row.cells[2].textContent.toLowerCase();

        const passesTextFilter = rowText.includes(textFilter);
        const passesProjectFilter = !projectFilter || projectCellText === projectFilter;

        row.style.display = (passesTextFilter && passesProjectFilter) ? "" : "none";
    });
}
</script>

<!-- Styling -->
<style>
.content-container {
    padding: 20px 25px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.page-header h2 {
    color: #000;
    font-size: 22px;
    font-weight: 700;
    margin: 0;
}
/* REMOVED: .btn-primary styles from page-header context */
.card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    padding: 20px;
}
.table-responsive {
    overflow-x: auto;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table thead {
    background-color: #f5f5f5;
    color: #000;
}
/* Reduced padding and font size for narrow columns (Optional Improvement) */
.data-table th, .data-table td {
    padding: 10px 6px; 
    text-align: center;
    border-bottom: 1px solid #ddd;
    font-size: 13px; /* Reduced font size */
}
.data-table th {
    font-weight: 600;
    text-transform: uppercase;
}
/* Low Stock Indicator Style */
.data-table .low-stock {
    background-color: #fff4f4;
}
.data-table .low-stock:hover {
    background-color: #ffeaea;
}
.data-table tr:hover {
    background-color: #f9fafb;
}
.data-table .highlight {
    font-weight: 600;
    color: #2c3e50;
}

/* Filter Bar Style */
.filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}
.filter-bar input, .filter-bar select {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
    width: 48%;
    box-sizing: border-box;
}

/* REMOVED: Action Buttons Styles */
/* .data-table .actions { ... } */
/* .data-table .actions a { ... } */
/* .data-table .actions a.edit { ... } */
/* .data-table .actions a.deduct { ... } */
/* .data-table .actions a.delete { ... } */
/* .data-table .actions a:hover { ... } */

.empty {
    text-align: center;
    padding: 15px;
    color: #777;
}

/* REMOVED: Responsive Tweak for Actions */
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>