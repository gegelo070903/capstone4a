<?php
include 'includes/db.php';
include 'includes/header.php';

if (is_constructor()):
?>
<div class="add-material-form-container">
    <div class="add-material-form">
        <h3>Submit Daily Report</h3>
        <form action="submit_report.php" method="POST" enctype="multipart/form-data">
            <label>Date:</label>
            <input type="date" name="report_date" required>
            
            <label>Start Time:</label>
            <input type="time" name="start_time" required>
            
            <label>End Time:</label>
            <input type="time" name="end_time">
            
            <label>Status:</label>
            <div style="margin-bottom: 20px;">
                <input type="radio" name="status" value="ongoing" checked> Ongoing
                <input type="radio" name="status" value="complete"> Complete
            </div>
            
            <label>Description:</label>
            <textarea name="description" rows="4" style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; margin-bottom: 20px;" required></textarea>
            
            <label>Materials Left:</label>
            <input type="text" name="materials_left">
            
            <label>Proof Image:</label>
            <input type="file" name="proof_image" accept="image/*" style="margin-bottom: 20px;">
            
            <button type="submit">Submit Report</button>
        </form>
    </div>
</div>
<?php endif; ?>

<h2 class="supply-monitoring-header">Daily Reports</h2>

<table>
    <tr>
        <th>Date</th>
        <th>Start</th>
        <th>End</th>
        <th>Status</th>
        <th>Description</th>
        <th>Materials Left</th>
        <th>Proof</th>
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
            <?php if ($r['proof_image'] && file_exists($r['proof_image'])): ?>
                <img src="<?= htmlspecialchars($r['proof_image']) ?>" width="80">
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

</div>
</body>
</html>