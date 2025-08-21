<?php
include 'includes/db.php';
include 'includes/header.php';

date_default_timezone_set('Asia/Manila');
if (!is_admin()) {
    header("Location: supply_monitoring.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $price = floatval($_POST['price']);
    $supplier = $conn->real_escape_string($_POST['supplier']);
    $date = date('Y-m-d');
    $time = date('h:i:s A');
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

<div class="add-material-form-container">
    <div class="add-material-form">
        <h3>Add Material</h3>
        <form method="POST">
            <label>Name:</label>
            <input name="name" type="text" required>
            
            <label>Price:</label>
            <input name="price" type="number" step="0.01" required>
            
            <label>Supplier:</label>
            <input name="supplier" type="text" required>
            
            <label>Purpose:</label>
            <input name="purpose" type="text" required>
            
            <label>Quantity:</label>
            <input name="quantity" type="number" min="1" required>
            
            <button type="submit">Add Material</button>
        </form>
    </div>
</div>

</div>
</body>
</html>