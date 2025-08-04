<?php
include 'includes/db.php';
include 'includes/header.php';

$result = $conn->query("SELECT * FROM materials");
?>
<style>
.main-content {
    margin-left: 110px;
    min-height: 10vh;
    padding-top: 10px; /* adds a little space at the top */
}

.supply-monitoring-header {
    text-align: center;
    margin-bottom: 4px;
    margin-top: 10px;
}
.add-material-link {
    display: block;
    text-align: right;
    margin-bottom: 18px;
    margin-top: 20px;
}

/* Table styling */
table {
    border-collapse: collapse;
    margin: 0 auto;
    width: 95%;            /* Responsive width */
    background: #fff;
}

th, td {
    border: 1px solid #222;
    padding: 10px 15px;
    text-align: center;
    vertical-align: middle;
}

/* Make ID column narrower */
th:first-child,
td:first-child {
    min-width: 35px;
    width: 35px;
    padding-left: 5px;
    padding-right: 5px;
}

th {
    background: #f5f5f5;
    font-weight: bold;
}
tr {
    background: #fff;
}
tr:nth-child(even) {
    background: #f9f9f9;
}
</style>
<div class="main-content">
<h2 class="supply-monitoring-header">Supply Monitoring</h2>
<?php if (is_admin()): ?>
<a href="add_material.php" class="add-material-link">Add Material</a>
<?php endif; ?>
<table>
  <tr>
    <th>ID</th>
    <th>Name</th>
    <th>Price</th>
    <th>Supplier</th>
    <th>Date</th>
    <th>Time</th>
    <th>Purpose</th>
    <th>Quantity</th>
    <th>Total</th>
    <?php if (is_admin()): ?><th>Action</th><?php endif; ?>
  </tr>
  <?php while($row = $result->fetch_assoc()): ?>
  <tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['name']) ?></td>
    <td><?= $row['price'] ?></td>
    <td><?= htmlspecialchars($row['supplier']) ?></td>
    <td><?= $row['date'] ?></td>
    <td><?= $row['time'] ?></td>
    <td><?= htmlspecialchars($row['purpose']) ?></td>
    <td><?= $row['quantity'] ?></td>
    <td><?= $row['total_amount'] ?></td>
    <?php if (is_admin()): ?>
    <td>
      <a href="edit_material.php?id=<?= $row['id'] ?>">Edit</a> |
      <a href="delete_material.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete?')">Delete</a>
    </td>
    <?php endif; ?>
  </tr>
  <?php endwhile; ?>
</table>
</div>
</body>
</html>