<?php
include 'includes/db.php';
include 'includes/header.php';

$result = $conn->query("SELECT * FROM materials");
?>

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
        <td><?= number_format($row['price'], 0) ?></td>
        <td><?= htmlspecialchars($row['supplier']) ?></td>
        <td><?= $row['date'] ?></td>
        <td><?= $row['time'] ?></td>
        <td><?= htmlspecialchars($row['purpose']) ?></td>
        <td><?= $row['quantity'] ?></td>
        <td><?= number_format($row['total_amount'],0) ?> </td>
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