<?php
// ===============================================================
// uploads/projects.php  — Projects grid with sticky header,
// search, sort, add overlay, fade-in cards, and progress bars
// ===============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';
require_login();

$user_role = $_SESSION['user_role'] ?? 'constructor';

// Fetch projects (no join; matches your schema screenshot)
$stmt = $conn->prepare("
    SELECT id, name, location, units, progress, status, created_at
    FROM projects
    ORDER BY id DESC
");
$stmt->execute();
$projects = $stmt->get_result();
?>
<style>
:root{
  --brand-blue:#2563eb;
  --brand-blue-dark:#1d4ed8;
  --gray-50:#f8fafc;
  --gray-100:#f3f4f6;
  --gray-200:#e5e7eb;
  --gray-400:#9ca3af;
  --ink:#111827;
  --text:#1f2937;
  --white:#fff;
  --ok:#22c55e;
  --warn:#fbbf24;
}
body{background:var(--gray-100);font-family:'Inter','Segoe UI',sans-serif;color:var(--text);overflow-x:hidden}

/* main */
.main-container{
  margin-left:5px;
  padding:20px;
  min-height:100vh;
  box-sizing:border-box;
  background:var(--gray-50);
}

/* sticky header container (locked) */
.projects-header-container{
  background:#fff;
  border-radius:12px;
  padding:20px 24px;
  box-shadow:0 4px 12px rgba(0,0,0,.08);
  margin-bottom:22px;
  position:sticky;
  top:15px;
  z-index:200;
}
.header-row{
  display:flex;
  justify-content:space-between;
  align-items:center;
  flex-wrap:wrap;
  gap:14px;
}
h2{
  margin:0;
  font-size:28px;
  color:var(--ink);
  font-weight:700;
}
.actions-row{display:flex;align-items:center;gap:12px}
.search-box input{
  padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;width:230px;
}
.search-box input:focus{outline:none;border-color:var(--brand-blue);box-shadow:0 0 0 3px rgba(37,99,235,.2)}
.sort-select{
  padding:8px 10px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;min-width:140px
}
.btn-add{
  background:var(--brand-blue);color:#fff;padding:10px 18px;border-radius:8px;border:none;
  font-weight:600;cursor:pointer;font-size:14px;transition:.2s;
}
.btn-add:hover{background:var(--brand-blue-dark);box-shadow:0 4px 8px rgba(0,0,0,.15)}

/* grid */
.projects-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(310px,1fr));
  gap:26px;
  margin-top:6px;
}

/* card */
.project-card{
  background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:18px 18px 20px;
  box-shadow:0 2px 6px rgba(0,0,0,.08);position:relative;cursor:pointer;animation:fadeUp .38s ease both;
  transition:transform .15s ease, box-shadow .15s ease;
}
.project-card:hover{transform:translateY(-3px);box-shadow:0 10px 18px rgba(0,0,0,.09)}
.project-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.project-title{font-size:18px;font-weight:700;color:var(--ink);margin-right:10px}

/* status chips */
.status-badge{padding:4px 10px;border-radius:999px;font-size:12px;color:#fff;font-weight:700}
.status-badge.Pending{background:var(--warn)}
.status-badge.Ongoing{background:var(--brand-blue)}
.status-badge.Completed{background:var(--ok)}

/* details */
.project-details{font-size:14px;color:#6b7280;line-height:1.55;margin-top:8px}
.project-details p{margin:3px 0}

/* ellipsis menu */
.menu-icon{
  font-size:20px;color:#6b7280;cursor:pointer;padding:4px 6px;border-radius:6px;user-select:none
}
.menu-icon:hover{background:var(--gray-100);color:var(--ink)}
.menu{
  position:absolute;right:10px;top:36px;background:#fff;border:1px solid var(--gray-200);border-radius:10px;
  min-width:140px;box-shadow:0 6px 18px rgba(0,0,0,.12);display:none;z-index:10
}
.menu a{
  display:block;padding:10px 12px;font-size:14px;color:#374151;text-decoration:none;border-radius:8px;margin:6px
}
.menu a:hover{background:#eff6ff;color:var(--brand-blue)}
.menu.show{display:block}

/* progress */
.progress-wrap{margin-top:10px;margin-bottom:6px}
.progress-label{font-size:12px;color:#6b7280;margin-bottom:6px}
.progress-bar{
  height:10px;border-radius:999px;background:#e5e7eb;overflow:hidden;position:relative
}
.progress-fill{
  height:100%;width:0;border-radius:999px;background:var(--brand-blue);transition:width .7s ease;
}
.progress-num{
  position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);
  font-size:11px;color:#fff;font-weight:700;pointer-events:none;mix-blend-mode:luminosity;
}

/* overlay (add project) */
.overlay{
  position:fixed;inset:0;background:rgba(0,0,0,.65);display:none;align-items:center;justify-content:center;z-index:5000
}
.overlay-card{
  width:100%;max-width:600px;background:#fff;border-radius:12px;padding:28px 26px;position:relative;
  box-shadow:0 12px 30px rgba(0,0,0,.32);animation:zoomIn .18s ease both
}
.close-btn{
  position:absolute;top:10px;right:12px;background:none;border:none;font-size:22px;color:#6b7280;cursor:pointer
}
.close-btn:hover{color:var(--ink)}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;margin-top:14px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group label{font-weight:600;color:#374151;font-size:13.5px}
.form-group input, .form-group select{
  padding:10px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px
}
.form-group input:focus,.form-group select:focus{
  outline:none;border-color:var(--brand-blue);box-shadow:0 0 0 3px rgba(37,99,235,.2)
}
.overlay-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:6px}
.btn-cancel{background:#6b7280;color:#fff;border:none;border-radius:8px;padding:10px 16px;font-weight:700;cursor:pointer}
.btn-cancel:hover{background:#4b5563}
.btn-primary{background:var(--brand-blue);color:#fff;border:none;border-radius:8px;padding:10px 16px;font-weight:700;cursor:pointer}
.btn-primary:hover{background:var(--brand-blue-dark)}

/* anims */
@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes zoomIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}

@media (max-width:640px){.form-grid{grid-template-columns:1fr}}
</style>

<div class="main-container">

  <!-- Sticky header tools -->
  <div class="projects-header-container">
    <div class="header-row">
      <h2>Projects</h2>
      <div class="actions-row">
        <div class="search-box">
          <input type="text" id="searchInput" placeholder="Search project or location…">
        </div>
        <select id="sortSelect" class="sort-select">
          <option value="latest">Sort by: Latest</option>
          <option value="oldest">Oldest</option>
          <option value="az">A–Z</option>
          <option value="za">Z–A</option>
          <option value="progress">Progress</option>
        </select>
        <?php if ($user_role === 'admin'): ?>
          <button class="btn-add" onclick="toggleOverlay(true)">+ Add Project</button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Cards -->
  <div class="projects-grid" id="projectGrid">
    <?php if ($projects->num_rows > 0): ?>
      <?php while ($p = $projects->fetch_assoc()):
        $id   = (int)$p['id'];
        $name = $p['name'] ?? '';
        $location = $p['location'] ?? '';
        $units = (int)($p['units'] ?? 0);
        $progress = max(0, min(100, (int)($p['progress'] ?? 0)));
        $status = $p['status'] ?? 'Pending';
        $created = $p['created_at'] ?? '';
        $created_ts = strtotime($created) ?: 0;

        // pick progress color
        $fillColor = 'var(--brand-blue)';
        if ($progress >= 76) $fillColor = 'var(--ok)';
        elseif ($progress <= 25) $fillColor = '#9ca3af';
      ?>
      <div class="project-card"
           data-name="<?= htmlspecialchars(mb_strtolower($name)) ?>"
           data-location="<?= htmlspecialchars(mb_strtolower($location)) ?>"
           data-date="<?= $created_ts ?>"
           data-progress="<?= $progress ?>"
           onclick="window.location.href='../modules/view_project.php?id=<?= $id ?>'">
        <div class="project-header" onclick="event.stopPropagation();">
          <div class="project-title"><?= htmlspecialchars($name) ?></div>
          <div style="display:flex;align-items:center;gap:8px;">
            <span class="status-badge <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span>
            <?php if ($user_role === 'admin'): ?>
              <span class="menu-icon" onclick="toggleMenu(this)">⋮</span>
              <div class="menu">
                <a href="../modules/edit_project.php?id=<?= $id ?>">Edit</a>
                <a href="../modules/delete_project.php?id=<?= $id ?>" onclick="return confirm('Delete this project?');">Delete</a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Progress -->
        <div class="progress-wrap">
          <div class="progress-label">Progress: <strong><?= $progress ?>%</strong></div>
          <div class="progress-bar">
            <div class="progress-fill" style="width:<?= $progress ?>%; background:<?= $fillColor ?>;"></div>
            <div class="progress-num"><?= $progress ?>%</div>
          </div>
        </div>

        <div class="project-details">
          <p><strong>Location:</strong> <?= htmlspecialchars($location ?: '—') ?></p>
          <p><strong>Units:</strong> <?= $units ?></p>
          <p><strong>Created:</strong> <?= $created ? date('M d, Y', strtotime($created)) : '—' ?></p>
        </div>
      </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p style="color:#6b7280;">No projects found.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Add Project Overlay -->
<div class="overlay" id="addOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleOverlay(false)" aria-label="Close">✕</button>
    <h3 style="margin:0 0 6px;font-size:20px;color:var(--ink);font-weight:800;">Add New Project</h3>

    <form method="post" action="../modules/add_project.php" onsubmit="return validateAddForm();">
      <div class="form-grid">
        <div class="form-group">
          <label for="pname">Project Name</label>
          <input type="text" id="pname" name="name" required>
        </div>
        <div class="form-group">
          <label for="plocation">Location</label>
          <input type="text" id="plocation" name="location" required>
        </div>

        <div class="form-group">
          <label for="punits">Number of Units / Houses</label>
          <input type="number" id="punits" name="units" min="0" step="1" value="0" required>
        </div>

        <div class="form-group">
          <label for="pstatus">Status</label>
          <select id="pstatus" name="status" required>
            <option value="Pending">Pending</option>
            <option value="Ongoing">Ongoing</option>
            <option value="Completed">Completed</option>
          </select>
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
// --- menu toggler
function toggleMenu(el){
  const m = el.nextElementSibling;
  document.querySelectorAll('.menu').forEach(x=>{ if(x!==m) x.classList.remove('show'); });
  m.classList.toggle('show');
}
document.addEventListener('click', e=>{
  if(!e.target.closest('.menu') && !e.target.closest('.menu-icon')){
    document.querySelectorAll('.menu').forEach(x=>x.classList.remove('show'));
  }
});

// --- overlay
function toggleOverlay(show){
  document.getElementById('addOverlay').style.display = show ? 'flex' : 'none';
}
function validateAddForm(){
  const n = document.getElementById('pname').value.trim();
  const l = document.getElementById('plocation').value.trim();
  const u = parseInt(document.getElementById('punits').value || '0',10);
  if(!n || !l || isNaN(u) || u < 0){ alert('Please fill in all required fields.'); return false; }
  return true;
}

// --- search + sort
const grid = document.getElementById('projectGrid');
const cards = Array.from(grid.children);

document.getElementById('searchInput').addEventListener('input', function(){
  const q = this.value.trim().toLowerCase();
  cards.forEach(card=>{
    const nm = card.dataset.name;
    const loc = card.dataset.location || '';
    const match = nm.includes(q) || loc.includes(q);
    card.style.display = match ? '' : 'none';
  });
});

document.getElementById('sortSelect').addEventListener('change', function(){
  const val = this.value;
  const visible = cards.filter(c => c.style.display !== 'none');

  visible.sort((a,b)=>{
    if(val === 'latest')   return +b.dataset.date - +a.dataset.date;
    if(val === 'oldest')   return +a.dataset.date - +b.dataset.date;
    if(val === 'az')       return (a.dataset.name > b.dataset.name) ? 1 : -1;
    if(val === 'za')       return (a.dataset.name < b.dataset.name) ? 1 : -1;
    if(val === 'progress') return (+b.dataset.progress) - (+a.dataset.progress);
    return 0;
  });

  visible.forEach(el => grid.appendChild(el));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>