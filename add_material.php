<?php
include 'includes/db.php';
include 'includes/header.php';

date_default_timezone_set('Asia/Manila'); // timezone Phillipines
if (!is_admin()) {
    header("Location: supply_monitoring.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $price = floatval($_POST['price']);
    $supplier = $conn->real_escape_string($_POST['supplier']);
    $date = date('Y-m-d'); // Automatically set to today
    $time = date('h:i:s A'); // Automatically set to current time
    $purpose = $conn->real_escape_string($_POST['purpose']);
    $quantity = intval($_POST['quantity']);
    $total = $price * $quantity;

    $stmt = $conn->prepare("INSERT INTO materials (name, price, supplier, date, time, purpose, quantity, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdsssisd", $name, $price, $supplier, $date, $time, $purpose, $quantity, $total);
    $stmt->execute();
    header("Location: supply_monitoring.php");
    exit();
}
?>
<style>
form {
    width: 350px;
    margin: 40px auto 0 auto;
}
form label {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 10px;
}
form label span {
    width: 100px;
    text-align: right;
    margin-right: 10px;
    display: inline-block;
}
form input {
    flex: 5;
}
</style>
<h2>Add Material</h2>
<form method="POST">
    <label><span>Name:</span> <input name="name" required></label><br>
    <label><span>Price:</span> <input name="price" type="number" step="0.01" required></label><br>
    <label><span>Supplier:</span> <input name="supplier" required></label><br>
    <label><span>Purpose:</span> <input name="purpose" required></label><br>
    <label><span>Quantity:</span> <input name="quantity" type="number" min="1" required></label><br>
    <button type="submit">Add</button>
</form>
</form>
</div>
</body>
</html>