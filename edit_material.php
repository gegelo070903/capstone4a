<?php
// admin add materials and edit materials for project
include 'includes/db.php';
include 'includes/header.php';

if (!is_admin()) {
    header("Location: supply_monitoring.php");
    exit();
}
$id = intval($_GET['id']);
$res = $conn->query("SELECT * FROM materials WHERE id=$id");
$row = $res->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $price = floatval($_POST['price']);
    $supplier = $conn->real_escape_string($_POST['supplier']);
    $purpose = $conn->real_escape_string($_POST['purpose']);
    $quantity = intval($_POST['quantity']);
    $total = $price * $quantity;

    // Only update editable fields, keep original date and time
    $stmt = $conn->prepare("UPDATE materials SET name=?, price=?, supplier=?, purpose=?, quantity=?, total_amount=? WHERE id=?");
    $stmt->bind_param("sdssidi", $name, $price, $supplier, $purpose, $quantity, $total, $id);
    $stmt->execute();
    header("Location: supply_monitoring.php");
    exit();
}
?>
<h2>Edit Material</h2>
<form method="POST">
    <label>Name: <input name="name" value="<?= htmlspecialchars($row['name']) ?>" required></label><br>
    <label>Price: <input name="price" type="number" step="0.01" value="<?= $row['price'] ?>" required></label><br>
    <label>Supplier: <input name="supplier" value="<?= htmlspecialchars($row['supplier']) ?>" required></label><br>
    <label>Date: <input name="date" type="date" value="<?= $row['date'] ?>" readonly></label><br>
    <label>Time: <input name="time" type="time" value="<?= $row['time'] ?>" readonly></label><br>
    <label>Purpose: <input name="purpose" value="<?= htmlspecialchars($row['purpose']) ?>" required></label><br>
    <label>Quantity: <input name="quantity" type="number" min="1" value="<?= $row['quantity'] ?>" required></label><br>
    <button type="submit">Update</button>
</form>
</div>
</body>
</html>