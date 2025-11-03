<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../users/login.php");
    exit();
}

// Handle sorting logic
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

// Fetch active projects (exclude archived)
$query = "SELECT id, name, location, status, created_at 
          FROM projects 
          WHERE status != 'Archived'
          ORDER BY $order_by";
$result = $conn->query($query);
?>

<div class="content-wrapper">
  
  <!-- 🔍 Reports Header Container -->
  <div class="header-container">
    <div class="reports-header">
      <h2>Reports</h2>

      <div class="controls">
        <input 
          type="text" 
          id="searchInput" 
          placeholder="Search project or location..." 
          onkeyup="filterProjects()"
        >
        <form method="GET" id="sortForm">
          <select name="sort" onchange="document.getElementById('sortForm').submit();">
            <option value="latest" <?= $sort_option === 'latest' ? 'selected' : '' ?>>Sort: Latest</option>
            <option value="oldest" <?= $sort_option === 'oldest' ? 'selected' : '' ?>>Sort: Oldest</option>
            <option value="name" <?= $sort_option === 'name' ? 'selected' : '' ?>>Sort: A–Z</option>
          </select>
        </form>
      </div>
    </div>
  </div>

  <!-- 🧱 Project Grid -->
  <div class="projects-grid" id="projectGrid">
    <?php if ($result && $result->num_rows > 0): ?>
      <?php while ($proj = $result->fetch_assoc()): ?>
        <div class="project-card" 
             data-name="<?= strtolower($proj['name']) ?>" 
             data-location="<?= strtolower($proj['location']) ?>"
             onclick="window.location.href='view_units.php?project_id=<?= $proj['id'] ?>'">

          <div class="project-header">
            <h3><?= htmlspecialchars($proj['name']) ?></h3>
            <span class="status <?= strtolower($proj['status']) ?>">
              <?= htmlspecialchars($proj['status']) ?>
            </span>
          </div>
          <p><strong>Location:</strong> <?= htmlspecialchars($proj['location']) ?></p>
          <p><strong>Created:</strong> <?= date('M d, Y', strtotime($proj['created_at'])) ?></p>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="no-data">No active projects found.</p>
    <?php endif; ?>
  </div>
</div>

<script>
function filterProjects() {
  const input = document.getElementById('searchInput').value.toLowerCase();
  const cards = document.querySelectorAll('.project-card');

  cards.forEach(card => {
    const name = card.getAttribute('data-name');
    const location = card.getAttribute('data-location');
    card.style.display = (name.includes(input) || location.includes(input)) ? 'block' : 'none';
  });
}
</script>

<style>
/* === Layout Wrapper === */
.content-wrapper {
  padding: 20px;
  background: #f8fafc;
}

/* === Header Container === */
.header-container {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding:20px 24px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  margin-bottom: 25px;
}

/* === Reports Header Row === */
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

/* === Controls (Search + Sort) === */
.controls {
  display: flex;
  gap: 10px;
  align-items: center;
}

.controls input {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  width: 230px;
  font-size: 14px;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.controls input:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
}

.controls select {
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  background-color: #fff;
  font-size: 14px;
  cursor: pointer;
}

/* === Project Grid === */
.projects-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 20px;
}

/* === Project Card === */
.project-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 18px 20px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  transition: all 0.2s ease;
  cursor: pointer;
}
.project-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 5px 12px rgba(0, 0, 0, 0.1);
  border-color: #2563eb;
}

/* === Project Header === */
.project-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.project-header h3 {
  margin: 0;
  font-size: 18px;
  color: #1f2937;
}
.status {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  text-transform: capitalize;
  color: #fff;
}
.status.ongoing { background-color: #2563eb; }
.status.completed { background-color: #16a34a; }
.status.paused { background-color: #f59e0b; }

/* === Card Text === */
.project-card p {
  font-size: 14px;
  color: #374151;
  margin: 4px 0;
}

/* === No Data === */
.no-data {
  text-align: center;
  color: #6b7280;
  font-style: italic;
}
</style>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
