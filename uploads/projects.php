<?php
// ===============================================================
// projects.php
// Displays list of projects and allows admin to add/edit/delete.
// ===============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';
require_login();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Fetch all projects
if ($user_role === 'admin') {
    $stmt = $conn->prepare("
        SELECT * FROM projects
        ORDER BY id DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT * FROM projects
        WHERE constructor_id = ?
        ORDER BY id DESC
    ");
    $stmt->bind_param('i', $user_id);
}
$stmt->execute();
$projects = $stmt->get_result();
?>

<style>
:root {
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
  margin-left: 5px;
  padding: 20px;
  min-height: 100vh;
  box-sizing: border-box;
  background-color: #f8fafc;
}

/* ===== FLOATING HEADER CONTAINER ===== */
.projects-header-container {
  background: #ffffff;
  border-radius: 12px;
  padding: 20px 30px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  margin-bottom: 30px;
  position: sticky;
  top: 15px;
  z-index: 200;
}

/* ===== HEADER ROW ===== */
.header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
}

h2 {
  font-size: 28px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

/* ===== ACTIONS ===== */
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
  margin-top: 20px;
}

.project-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  border: 1px solid #e5e7eb;
  transition: all 0.2s ease;
  position: relative;
  animation: fadeUp 0.4s ease;
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
.show-menu { display: block !important; }

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
  z-index: 5000;
}
.overlay-card {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 600px;
  padding: 35px 40px;
  position: relative;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
  animation: fadeIn 0.2s ease;
}

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

@keyframes fadeIn {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ===== Overlay Form Layout ===== */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-weight: 600;
  color: #374151;
  font-size: 14px;
}

.form-group input {
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  width: 100%;
  box-sizing: border-box;
}

.form-group input:focus {
  border-color: #2563eb;
  outline: none;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.2);
}

.overlay-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 10px;
}

.btn-cancel,
.btn-primary {
  padding: 10px 18px;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  font-size: 14px;
}

.btn-cancel {
  background-color: #6b7280;
  color: white;
  transition: background 0.2s;
}
.btn-cancel:hover {
  background-color: #4b5563;
}

.btn-primary {
  background-color: #2563eb;
  color: white;
  transition: background 0.2s, box-shadow 0.2s;
}
.btn-primary:hover {
  background-color: #1d4ed8;
  box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

@media (max-width: 600px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<div class="main-container">
  <div class="projects-header-container">
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
  </div>

  <div class="projects-grid" id="projectGrid">
    <?php if ($projects->num_rows > 0): ?>
      <?php while ($p = $projects->fetch_assoc()): ?>
        <div class="project-card" 
             data-name="<?= strtolower($p['name']) ?>" 
             data-date="<?= $p['created_at'] ?>" 
             onclick="window.location.href='../modules/view_project.php?id=<?= $p['id'] ?>'">
          <div class="project-header">
            <div class="project-title"><?= htmlspecialchars($p['name']) ?></div>
            <div class="ellipsis-menu" onclick="event.stopPropagation(); toggleMenu(this)">
              <span class="menu-icon">⋮</span>
              <div class="menu-dropdown">
                <a href="../modules/edit_project.php?id=<?= $p['id'] ?>">Edit</a>
                <a href="../modules/delete_project.php?id=<?= $p['id'] ?>" onclick="return confirm('Delete this project?');">Delete</a>
              </div>
            </div>
          </div>
          <span class="status-badge <?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars(ucfirst($p['status'])) ?></span>
          <div class="project-details">
            <p><strong>Location:</strong> <?= htmlspecialchars($p['location'] ?? '—') ?></p>
            <p><strong>Units:</strong> <?= htmlspecialchars($p['units']) ?></p>
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
    <h3 style="margin-bottom: 20px; color:#111827;">Add New Project</h3>

    <form action="../modules/process_add_project.php" method="POST" style="display: flex; flex-direction: column; gap: 15px;">
      <div class="form-grid">
        <div class="form-group">
          <label for="project_name">Project Name</label>
          <input type="text" id="project_name" name="project_name" required>
        </div>

        <div class="form-group">
          <label for="location">Location</label>
          <input type="text" id="location" name="location" required>
        </div>

        <div class="form-group">
          <label for="start_date">Start Date</label>
          <input type="date" id="start_date" name="start_date" required>
        </div>

        <div class="form-group">
          <label for="units">Number of Units / Houses</label>
          <input type="number" id="units" name="units" min="1" required>
        </div>
      </div>

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
    const nameA = a.dataset.name;
    const nameB = b.dataset.name;
    const dateA = new Date(a.dataset.date);
    const dateB = new Date(b.dataset.date);

    switch (this.value) {
      case 'latest': return dateB - dateA;
      case 'oldest': return dateA - dateB;
      case 'az': return nameA.localeCompare(nameB);
      case 'za': return nameB.localeCompare(nameA);
    }
  });

  sorted.forEach(card => grid.appendChild(card));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>