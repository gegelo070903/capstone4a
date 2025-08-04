<?php
include 'includes/db.php';
include 'includes/header.php';
?>
<html>
<h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>
<p>
    This is your dashboard.<br>
    Use the navigation to access different modules.
</p>
</div>
</body>
</html>