<?php
include 'includes/db.php';
include 'includes/header.php';

if (is_constructor()):
?>
<h2>Submit Daily Report</h2>
<form action="submit_report.php" method="POST" enctype="multipart/form-data">
    <label>Date: <input type="date" name="report_date" required></label><br>
    <label>Start Time: <input type="time" name="start_time" required></label><br>
    <label>End Time: <input type="time" name="end_time"></label><br>
    <label>Status:
        <input type="radio" name="status" value="ongoing" checked> Ongoing
        <input type="radio" name="status" value="complete"> Complete
    </label><br>
    <label>Description: <textarea name="description" required></textarea></label><br>
    <label>Materials Left: <input type="text" name="materials_left"></label><br>
    <label>Proof Image: <input type="file" name="proof_image" accept="image/*"></label><br>
    <button type="submit">Submit Report</button>
</form>
<?php endif; ?>

<h2>Daily Reports</h2>
<table>
    <tr>
        <th>Date</th><th>Start</th><th>End</th><th>Status</th><th>Description</th><th>Materials Left</th><th>Proof</th>
    </tr>
    <?php
    $where = is_constructor() ? "WHERE constructor_id=" . $_SESSION['user_id'] : "";
    $res = $conn->query("SELECT * FROM construction_reports $where ORDER BY report_date DESC");
    while($r = $res->fetch_assoc()):
    ?>
    <tr>
        <td><?= $r['report_date'] ?></td>
        <td><?= $r['start_time'] ?></td>
        <td><?= $r['end_time'] ?></td>
        <td><?= $r['status'] ?></td>
        <td><?= htmlspecialchars($r['description']) ?></td>
        <td><?= htmlspecialchars($r['materials_left']) ?></td>
        <td>
            <?php if ($r['proof_image']): ?>
                <img src="<?= htmlspecialchars($r['proof_image']) ?>" width="80">
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
</div>
</body>
</html>