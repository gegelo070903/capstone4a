<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Fetch all projects
if ($user_role === 'admin') {
    $stmt = $conn->prepare("
        SELECT p.*, u.username AS constructor_name
        FROM projects p
        LEFT JOIN users u ON u.id = p.constructor_id
        ORDER BY p.id DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT p.*, u.username AS constructor_name
        FROM projects p
        LEFT JOIN users u ON u.id = p.constructor_id
        WHERE p.constructor_id = ?
        ORDER BY p.id DESC
    ");
    $stmt->bind_param('i', $user_id);
}
$stmt->execute();
$projects = $stmt->get_result();

require_once __DIR__ . '/includes/header.php';

// Fetch constructors for overlay form
$constructors = $conn->query("SELECT id, username FROM users WHERE role = 'constructor' ORDER BY username ASC");
?>

<style>
:root {
  --sidebar-width: 100px;
  --container-padding: px;
  --content-max-width: 1200px;
  --brand-blue: #2563eb;
  --gray-light: #f3f4f6;
}

body {
  background-color: var(--gray-light);
  font-family: 'Inter', 'Segoe UI', sans-serif;
  color: #1f2937;
  overflow-x: hidden;
}

/* ===== MAIN CONTAINER ===== */
.main-container {
  margin-left: var(--sidebar-width);
  padding: 40px 40px;
  min-height: 100vh;
  transition: margin-left 0.3s ease;
  box-sizing: border-box;
}

.header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
  margin-bottom: 20px;
}

h2 {
  font-size: 28px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.actions-row {
  display: flex;
  align-items: center;
  gap: 15px;
}

.search-box input {
  padding: 8px 14px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  width: 220px;
}
.search-box input:focus {
  border-color: var(--brand-blue);
  outline: none;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.2);
}

.sort-select {
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
}

.btn-add {
  background: var(--brand-blue);
  color: #fff;
  padding: 10px 20px;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 600;
  font-size: 15px;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-add:hover {
  background: #1d4ed8;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

/* ===== GRID ===== */
.projects-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
  gap: 30px;
  margin-top: 25px;
}

.project-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  position: relative;
  border: 1px solid #e5e7eb;
  transition: all 0.2s ease;
}
.project-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.08);
}

.project-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}
.project-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}

.status-badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  color: #fff;
  font-weight: 600;
}
.status-badge.Pending { background-color: #fbbf24; }
.status-badge.Ongoing { background-color: #3b82f6; }
.status-badge.Completed { background-color: #22c55e; }

.project-details {
  font-size: 14px;
  color: #6b7280;
  line-height: 1.5;
}

/* ===== MENU ===== */
.menu-icon {
  font-size: 22px;
  color: #6b7280;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 50%;
  transition: background 0.2s;
}
.menu-icon:hover {
  background-color: #e5e7eb;
  color: #111827;
}
.menu-dropdown {
  position: absolute;
  right: 0;
  top: 35px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  display: none;
  min-width: 140px;
  z-index: 10;
}
.menu-dropdown a {
  display: block;
  padding: 10px 15px;
  font-size: 14px;
  text-decoration: none;
  color: #374151;
}
.menu-dropdown a:hover {
  background-color: #eff6ff;
  color: #1d4ed8;
}
.show-menu {
  display: block !important;
}

/* ===== OVERLAY ===== */
.overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.65);
  display: none;
  justify-content: center;
  align-items: center;
  z-index: 5000; /* On top of sidebar */
}

.overlay-card {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 550px;
  padding: 35px 40px;
  position: relative;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.overlay-card h3 {
  font-size: 22px;
  font-weight: 700;
  margin-bottom: 20px;
}

.overlay-card label {
  display: block;
  font-weight: 600;
  margin-bottom: 6px;
}

.overlay-card input,
.overlay-card select {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 15px;
  margin-bottom: 15px;
}
.overlay-card input:focus,
.overlay-card select:focus {
  border-color: var(--brand-blue);
  outline: none;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.2);
}

.overlay-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.btn-primary {
  background: var(--brand-blue);
  color: white;
  border: none;
  padding: 9px 18px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
}
.btn-primary:hover { background: #1d4ed8; }
.btn-cancel {
  background: #6b7280;
  color: white;
}
.btn-cancel:hover { background: #4b5563; }

.close-btn {
  position: absolute;
  top: 12px;
  right: 15px;
  font-size: 22px;
  color: #6b7280;
  cursor: pointer;
  background: none;
  border: none;
}
.close-btn:hover { color: #111827; }
</style>

<div class="main-container">
  <div class="header-row">
    <h2>Projects</h2>
    <div class="actions-row">
      <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search project...">
      </div>
      <select id="sortSelect" class="sort-select">
        <option value="latest">Sort by: Latest</option>
        <option value="oldest">Oldest</option>
        <option value="az">A–Z</option>
        <option value="za">Z–A</option>
      </select>
      <?php if ($user_role === 'admin'): ?>
        <button class="btn-add" onclick="toggleOverlay(true)">+ Add Project</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="projects-grid" id="projectGrid">
    <?php if ($projects->num_rows > 0): ?>
      <?php while ($p = $projects->fetch_assoc()): ?>
        <div class="project-card" data-name="<?= strtolower($p['name']) ?>" data-date="<?= $p['created_at'] ?>" onclick="window.location.href='view_project.php?id=<?= $p['id'] ?>'">
          <div class="project-header">
            <div class="project-title"><?= htmlspecialchars($p['name']) ?></div>
            <div class="ellipsis-menu" onclick="event.stopPropagation(); toggleMenu(this)">
              <span class="menu-icon">⋮</span>
              <div class="menu-dropdown">
                <a href="edit_project.php?id=<?= $p['id'] ?>">Edit</a>
                <a href="delete_project.php?id=<?= $p['id'] ?>" onclick="return confirm('Delete this project?');">Delete</a>
              </div>
            </div>
          </div>
          <span class="status-badge <?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars(ucfirst($p['status'])) ?></span>
          <div class="project-details">
            <p><strong>Location:</strong> <?= htmlspecialchars($p['location'] ?? '—') ?></p>
            <p><strong>Constructor:</strong> <?= htmlspecialchars($p['constructor_name']) ?></p>
            <p><strong>Start:</strong> <?= date('M d, Y', strtotime($p['created_at'])) ?></p>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p style="color:#6b7280;">No projects found.</p>
    <?php endif; ?>
  </div>
</div>

<!-- ===== Add Project Overlay ===== -->
<div id="addProjectOverlay" class="overlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleOverlay(false)">&times;</button>
    <h3>Add New Project</h3>
    <form action="process_add_project.php" method="POST">
      <label>Project Name</label>
      <input type="text" name="project_name" required>

      <label>Location</label>
      <input type="text" name="location" required>

      <label>Start Date</label>
      <input type="date" name="start_date" required>

      <label>Constructor</label>
      <select name="constructor_id" required>
        <option value="">-- Select Constructor --</option>
        <?php while ($c = $constructors->fetch_assoc()): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['username']) ?></option>
        <?php endwhile; ?>
      </select>

      <div class="overlay-actions">
        <button type="button" class="btn-cancel" onclick="toggleOverlay(false)">Cancel</button>
        <button type="submit" class="btn-primary">Save Project</button>
      </div>
    </form>
  </div>
</div>

<script>
// ===== MENU =====
function toggleMenu(menu) {
  const dropdown = menu.querySelector('.menu-dropdown');
  document.querySelectorAll('.menu-dropdown').forEach(d => {
    if (d !== dropdown) d.classList.remove('show-menu');
  });
  dropdown.classList.toggle('show-menu');
}
document.addEventListener('click', e => {
  if (!e.target.closest('.ellipsis-menu')) {
    document.querySelectorAll('.menu-dropdown').forEach(d => d.classList.remove('show-menu'));
  }
});

// ===== OVERLAY =====
function toggleOverlay(show) {
  const overlay = document.getElementById('addProjectOverlay');
  overlay.style.display = show ? 'flex' : 'none';
}

// ===== SEARCH FILTER =====
const searchInput = document.getElementById('searchInput');
searchInput.addEventListener('input', function() {
  const term = this.value.toLowerCase();
  document.querySelectorAll('.project-card').forEach(card => {
    const name = card.dataset.name;
    card.style.display = name.includes(term) ? '' : 'none';
  });
});

// ===== SORT =====
const sortSelect = document.getElementById('sortSelect');
sortSelect.addEventListener('change', function() {
  const grid = document.getElementById('projectGrid');
  const cards = Array.from(grid.children);

  const sorted = cards.sort((a, b) => {
    if (this.value === 'latest') return new Date(b.dataset.date) - new Date(a.dataset.date);
    if (this.value === 'oldest') return new Date(a.dataset.date) - new Date(b.dataset.date);
    if (this.value === 'az') return a.dataset.name.localeCompare(b.dataset.name);
    if (this.value === 'za') return b.dataset.name.localeCompare(a.dataset.name);
  });

  sorted.forEach(card => grid.appendChild(card));
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
