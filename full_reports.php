<?php
include 'includes/db.php';
include 'includes/header.php';
?>
<h2>Full Reports</h2>
<button onclick="exportPDF()">Export to PDF</button>
<div id="report-content">
    <!-- Supply Table -->
    <h3>Supply Monitoring</h3>
    <table>
        <tr><th>ID</th><th>Name</th><th>Quantity</th><th>Supplier</th><th>Total</th></tr>
        <?php
        $materials = $conn->query("SELECT * FROM materials");
        while($m = $materials->fetch_assoc()): ?>
        <tr>
            <td><?= $m['id'] ?></td>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><?= $m['quantity'] ?></td>
            <td><?= htmlspecialchars($m['supplier']) ?></td>
            <td><?= $m['total_amount'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <!-- Reports Table -->
    <h3>Development Monitoring</h3>
    <table>
        <tr><th>Date</th><th>Status</th><th>Description</th></tr>
        <?php
        $reports = $conn->query("SELECT * FROM construction_reports");
        while($r = $reports->fetch_assoc()): ?>
        <tr>
            <td><?= $r['report_date'] ?></td>
            <td><?= $r['status'] ?></td>
            <td><?= htmlspecialchars($r['description']) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.html(document.getElementById('report-content'), {
        callback: function (doc) {
            doc.save('full_reports.pdf');
        },
        x: 10,
        y: 10
    });
}
</script>
</div>
</body>
</html>