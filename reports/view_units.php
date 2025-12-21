<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../users/login.php");
    exit();
}

if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    echo "<h3 style='color:red;'>Invalid project ID.</h3>";
    exit();
}

$project_id = (int)$_GET['project_id'];

// Fetch project info
$project = $conn->query("SELECT * FROM projects WHERE id = $project_id LIMIT 1")->fetch_assoc();
if (!$project) {
    echo "<h3 style='color:red;'>Project not found.</h3>";
    exit();
}

// Handle sorting
$sort_option = $_GET['sort'] ?? 'latest';
switch ($sort_option) {
    case 'oldest':
        $order_by = 'created_at ASC';
        break;
    case 'name':
        $order_by = 'name ASC';
        break;
    default:
        $order_by = 'created_at DESC';
        break;
}

// Fetch all units for this project
$query = "SELECT id, name, progress, created_at 
          FROM project_units 
          WHERE project_id = $project_id 
          ORDER BY $order_by";
$result = $conn->query($query);
?>

<div class="content-wrapper">
  
  <!-- 🔍 Header Container -->
  <div class="header-container">
    <div class="reports-header">
      <div>
        <h2><?= htmlspecialchars($project['name']) ?> — Project/Units</h2>
        <p class="subtitle">Location: <?= htmlspecialchars($project['location']) ?></p>
      </div>

      <div class="controls">
        <button class="btn-back" onclick="window.location.href='reports.php'">
          <i class="fas fa-arrow-left"></i> Back to Reports
        </button>
        <input 
          type="text" 
          id="searchInput" 
          placeholder="Search unit..." 
          onkeyup="filterUnits()"
        >
        <form method="GET" id="sortForm">
          <input type="hidden" name="project_id" value="<?= $project_id ?>">
          <select name="sort" onchange="document.getElementById('sortForm').submit();">
            <option value="latest" <?= $sort_option === 'latest' ? 'selected' : '' ?>>Sort: Latest</option>
            <option value="oldest" <?= $sort_option === 'oldest' ? 'selected' : '' ?>>Sort: Oldest</option>
            <option value="name" <?= $sort_option === 'name' ? 'selected' : '' ?>>Sort: A–Z</option>
          </select>
        </form>
        <button class="btn-generate" onclick="window.location.href='generate_project_pdf.php?project_id=<?= $project_id ?>'">
          Generate Project PDF
        </button>
      </div>
    </div>
  </div>

  <!-- 🧱 Unit Cards -->
  <div class="unit-grid" id="unitGrid">
    <?php if ($result && $result->num_rows > 0): ?>
      <?php while ($unit = $result->fetch_assoc()): ?>
        <div class="unit-card" 
             data-name="<?= strtolower($unit['name']) ?>"
             onclick="window.location.href='view_unit_reports.php?unit_id=<?= $unit['id'] ?>&project_id=<?= $project_id ?>'">

          <div class="unit-header">
            <h3><?= htmlspecialchars($unit['name']) ?></h3>
            <span class="progress-badge"><?= $unit['progress'] ?>%</span>
          </div>

          <div class="progress-bar">
            <div class="progress-fill" style="width: <?= $unit['progress'] ?>%;"></div>
          </div>

          <p><strong>Created:</strong> <?= date('M d, Y', strtotime($unit['created_at'])) ?></p>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="no-data">No units found for this project.</p>
    <?php endif; ?>
  </div>
</div>

<script>
function filterUnits() {
  const input = document.getElementById('searchInput').value.toLowerCase();
  const cards = document.querySelectorAll('.unit-card');

  cards.forEach(card => {
    const name = card.getAttribute('data-name');
    card.style.display = name.includes(input) ? 'block' : 'none';
  });
}
</script>

<style>
.content-wrapper {
  padding: 20px;
  background: #f8fafc;
}

/* === Header Container === */
.header-container {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 18px 24px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  margin-bottom: 25px;
  position: sticky;
  top: 0;
  z-index: 100;
}

.reports-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.reports-header h2 {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.subtitle {
  color: #6b7280;
  font-size: 14px;
}

/* === Controls (Search + Sort + Buttons) === */
.controls {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.controls input,
.controls select {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  background-color: #fff;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.controls input {
  width: 230px;
}
.controls input:focus,
.controls select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
}

.btn-generate {
  background: #2563eb;
  color: #fff;
  padding: 10px 16px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  font-size: 14px;
  transition: background-color 0.2s;
}
.btn-generate:hover {
  background: #1d4ed8;
}

/* === Back Button === */
.btn-back {
  background: #6b7280;
  color: #fff;
  padding: 10px 16px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  font-size: 14px;
  transition: background-color 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.btn-back:hover {
  background: #4b5563;
}

/* === Unit Cards === */
.unit-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 20px;
}
.unit-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 18px 20px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  transition: all 0.2s ease;
  cursor: pointer;
}
.unit-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 5px 12px rgba(0, 0, 0, 0.1);
  border-color: #2563eb;
}
.unit-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.unit-header h3 {
  font-size: 18px;
  color: #1f2937;
  margin: 0;
}
.progress-badge {
  background-color: #2563eb;
  color: #fff;
  padding: 3px 8px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
}
.progress-bar {
  background-color: #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  height: 8px;
  margin: 10px 0;
}
.progress-fill {
  height: 8px;
  background-color: #2563eb;
  transition: width 0.3s ease;
}
.unit-card p {
  font-size: 14px;
  color: #374151;
  margin: 4px 0;
}
.no-data {
  text-align: center;
  color: #6b7280;
  font-style: italic;
}
</style>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
