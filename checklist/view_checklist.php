<?php
// checklist/view_checklist.php

// Apply error reporting again for good measure during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if (!isset($_GET['project_id']) || !isset($_GET['unit_id'])) {
    die('<h3 style="color:red;">Invalid parameters provided.</h3>');
}

$project_id = (int)$_GET['project_id'];
$unit_id = (int)$_GET['unit_id'];

$project = $conn->query("SELECT name FROM projects WHERE id = $project_id")->fetch_assoc();
$unit = $conn->query("SELECT name FROM project_units WHERE id = $unit_id")->fetch_assoc();

// Define a directory for image uploads relative to this script
// NOTE: This is primarily for the processing script, but kept here for clarity.
$upload_dir = __DIR__ . '/../uploads/checklist_proofs/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true); 
}

// ✅ FINAL PATH FIX: Define the ROOT-RELATIVE path for image viewing
// This is the most reliable way to link to static files on any web server.
$base_image_path_relative = '/capstone/uploads/checklist_proofs/'; 

include '../includes/header.php';
?>

<div class="main-content-wrapper">
  <div class="project-header-card">
    <div class="header-row">
      <h2><?= htmlspecialchars($unit['name']); ?> — <?= htmlspecialchars($project['name']); ?></h2>
      <a href="../modules/view_project.php?id=<?= $project_id; ?>" class="btn-back">← Back to Project</a>
    </div>
    <p><strong>Unit ID:</strong> <?= $unit_id; ?></p>
  </div>

  <!-- Unified Checklist Container -->
  <div class="checklist-container">
    <div class="checklist-header">
      <h3>Checklist Items</h3>
      <button class="btn btn-primary" onclick="toggleAddOverlay(true)">+ Add New Item</button>
    </div>

    <!-- Checklist Table -->
    <div id="checklistTableContainer">
      <?php
      // ✅ MODIFIED: Get checklists and count images from new table
      $checklists = $conn->query("
        SELECT pc.*, 
               (SELECT COUNT(*) FROM checklist_images ci WHERE ci.checklist_id = pc.id) as image_count
        FROM project_checklists pc 
        WHERE pc.project_id = $project_id AND pc.unit_id = $unit_id 
        ORDER BY pc.id ASC
      ");
      if ($checklists->num_rows > 0): ?>
        <div class="checklist-table">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Description</th>
                <th>Status</th>
                <th>Proof</th> 
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 1; while($item = $checklists->fetch_assoc()): 
                $image_count = (int)$item['image_count'];
              ?>
                <tr>
                  <td><?= $i++; ?></td>
                  <td><?= htmlspecialchars($item['item_description']); ?></td>
                  <td>
                    <?php if ($item['is_completed']): ?>
                      <span class="status completed">Completed</span>
                    <?php else: ?>
                      <span class="status pending">Pending</span>
                    <?php endif; ?>
                  </td>
                  <td style="width:140px;">
                    <?php if ($image_count > 0): ?>
                       <button class="btn-primary action-btn btn-sm view-images-btn" 
                               data-id="<?= $item['id']; ?>"
                               data-item-desc="<?= htmlspecialchars($item['item_description']); ?>"
                               style="background:#16a34a !important;">View (<?= $image_count ?>) Image<?= $image_count > 1 ? 's' : '' ?></button>
                    <?php else: ?>
                       <button class="btn-primary action-btn btn-sm insert-image-btn" 
                               data-id="<?= $item['id']; ?>"
                               data-item-desc="<?= htmlspecialchars($item['item_description']); ?>">Insert Image</button>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($item['is_completed']): ?>
                      <button class="btn-cancel action-btn" data-action="uncheck" data-id="<?= $item['id']; ?>">Uncheck</button>
                    <?php else: ?>
                      <button class="btn-primary action-btn" data-action="check" data-id="<?= $item['id']; ?>">Mark Complete</button>
                    <?php endif; ?>
                    <button class="btn-delete delete-btn" data-id="<?= $item['id']; ?>">Delete</button>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:#888;">No checklist items yet for this unit.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ✅ ADD ITEM OVERLAY (UNCHANGED) -->
<div class="overlay" id="addItemOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleAddOverlay(false)">✕</button>
    <h3 class="overlay-title">Add Checklist Item</h3>
    <form id="addItemForm" method="POST" action="process_add_checklist_item.php">
      <input type="hidden" name="project_id" value="<?= $project_id; ?>">
      <input type="hidden" name="unit_id" value="<?= $unit_id; ?>">
      <input type="hidden" name="apply_mode" value="single">

      <div class="form-group">
        <label for="desc">Checklist Description:</label>
        <input type="text" id="desc" name="item_description" placeholder="Enter checklist description" required>
      </div>

      <div class="form-actions">
        <button type="button" class="btn-cancel" onclick="toggleAddOverlay(false)">Cancel</button>
        <button type="submit" class="btn-primary">Add Item</button>
      </div>
    </form>
  </div>
</div>

<!-- ✅ NEW: IMAGE UPLOAD OVERLAY -->
<div class="overlay" id="uploadImageOverlay">
  <div class="overlay-card">
    <button class="close-btn" onclick="toggleUploadOverlay(false)">✕</button>
    <h3 class="overlay-title" id="uploadTitle">Upload Proof Image</h3>
    
    <!-- IMPORTANT: Must use enctype="multipart/form-data" for file uploads -->
    <form id="uploadImageForm" method="POST" action="process_upload_checklist_image.php" enctype="multipart/form-data">
      <input type="hidden" name="checklist_id" id="upload_checklist_id">
      <input type="hidden" name="project_id" value="<?= $project_id; ?>">
      <input type="hidden" name="unit_id" value="<?= $unit_id; ?>">

      <div class="form-group">
        <label for="proof_file">Select Image File (JPG, PNG, or WEBP):</label>
        <input type="file" id="proof_file" name="proof_file" accept=".jpg, .jpeg, .png, .webp" required>
      </div>
      
      <p style="color:#dc2626; font-size:12px; margin-top:10px;" id="uploadError"></p>

      <div class="form-actions">
        <button type="button" class="btn-cancel" onclick="toggleUploadOverlay(false)">Cancel</button>
        <button type="submit" class="btn-primary">Upload Image</button>
      </div>
    </form>
  </div>
</div>

<!-- ✅ UPDATED: IMAGE GALLERY OVERLAY (Multiple Images) -->
<div class="overlay" id="viewImageOverlay">
  <div class="overlay-card" style="max-width: 700px;">
    <button class="close-btn" onclick="toggleViewOverlay(false)">✕</button>
    <h3 class="overlay-title" id="viewTitle">Checklist Proof Images</h3>
    
    <!-- Image Gallery Container -->
    <div id="imageGallery" style="display:flex; flex-wrap:wrap; gap:10px; margin-top:15px; max-height:400px; overflow-y:auto;">
      <!-- Images will be loaded here dynamically -->
      <p id="loadingImages" style="color:#888;">Loading images...</p>
    </div>
    
    <!-- Action buttons -->
    <input type="hidden" id="view_checklist_id" value="">
    <div class="form-actions" style="margin-top: 15px;">
      <button type="button" class="btn-delete" id="removeAllImagesBtn">Remove All</button>
      <button type="button" class="btn-primary" id="addNewImageBtn">+ Add New Image</button>
    </div>
  </div>
</div>

<!-- Single Image View Modal (for viewing one image larger) -->
<div class="overlay" id="singleImageOverlay">
  <div class="overlay-card" style="max-width: 800px;">
    <button class="close-btn" onclick="closeSingleImage()">✕</button>
    <h3 class="overlay-title" id="singleImageTitle">Image Preview</h3>
    <img id="singleProofImage" src="" alt="Proof Image" style="width:100%; max-height:500px; object-fit:contain; border-radius:8px; margin-top:10px;">
    <input type="hidden" id="single_image_id" value="">
    <div class="form-actions" style="margin-top: 15px;">
      <button type="button" class="btn-back-modal" onclick="closeSingleImage()">← Back to Gallery</button>
      <button type="button" class="btn-delete" id="removeSingleImageBtn">Remove This Image</button>
    </div>
  </div>
</div>

<style>
/* Existing Styles (Mostly UNCHANGED) */
.main-content-wrapper{padding:20px;}
.project-header-card{background:#fff;padding:20px;border-radius:8px;margin-bottom:20px;box-shadow:0 4px 10px rgba(0,0,0,0.08);}
.header-row{display:flex;justify-content:space-between;align-items:center;}
.btn-back{background:#374151;color:#fff;text-decoration:none;padding:8px 14px;border-radius:6px;}
.btn-back:hover{background:#111827;}

/* Unified Checklist Container */
.checklist-container {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.06);
  padding: 20px;
  margin-top: 15px;
}

.checklist-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}
.checklist-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}

/* Table Styles */
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;}
.status.completed{color:#22c55e;font-weight:600;}
.status.pending{color:#f59e0b;font-weight:600;}

/* Buttons */
.btn,
.btn-primary,
.btn-cancel,
.btn-delete {
  border: none;
  outline: none;
  box-shadow: none;
  cursor: pointer;
  font-weight: 600;
  border-radius: 6px;
  transition: background 0.2s ease, transform 0.1s ease;
  font-size: 13px; 
  padding: 8px 14px; 
}
.btn-primary {
  background: #2563eb;
  color: #fff;
}
.btn-primary:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
}
.btn-cancel {
  background: #6b7280;
  color: #fff;
}
.btn-cancel:hover {
  background: #4b5563;
  transform: translateY(-1px);
}
.btn-delete {
  background: #dc2626;
  color: #fff;
}
.btn-delete:hover {
  background: #b91c1c;
  transform: translateY(-1px);
}
/* Adjustments for action buttons in the table */
td button { 
    margin-right: 5px; 
    padding: 6px 10px; /* Smaller size for table buttons */
    white-space: nowrap; 
}

button:focus,
.btn:focus,
.btn-primary:focus,
.btn-cancel:focus,
.btn-delete:focus {
  outline: none !important;
  box-shadow: none !important;
}

/* Overlay */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,0.55);display:none;align-items:center;justify-content:center;z-index:10000;}
.overlay-card{background:#fff;border-radius:12px;width:100%;max-width:500px;padding:28px 34px 32px;box-shadow:0 12px 30px rgba(0,0,0,0.25);position:relative;animation:fadeIn 0.25s ease;}
.overlay-title{font-size:20px;font-weight:700;color:#111827;margin-bottom:20px;}
.close-btn{position:absolute;top:12px;right:14px;background:none;border:none;font-size:22px;color:#6b7280;cursor:pointer;}
.close-btn:hover{color:#111827;}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:14px;}
label{font-weight:600;color:#374151;font-size:14px;}
input[type="text"], input[type="file"]{padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:15px;width:100%;background:#fff;transition:border-color 0.2s ease, box-shadow 0.2s ease;}
input[type="text"]:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,0.2);}
.form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:10px;}
@keyframes fadeIn{from{opacity:0;transform:scale(0.96);}to{opacity:1;transform:scale(1);}}

/* Added styles to make the View Image button green, using existing color variable ideas */
.view-image-btn {
    background-color: #16a34a !important; /* var(--ok) equivalent from project.php */
}
.view-image-btn:hover {
    background-color: #15803d !important;
}
.btn-sm {
    font-size: 11px !important;
    padding: 4px 8px !important;
}

/* Back button style for modals */
.btn-back-modal {
    background: #374151;
    color: #fff;
    border: none;
    padding: 8px 14px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
    transition: background 0.2s ease;
}
.btn-back-modal:hover {
    background: #111827;
}

/* Image Gallery Styles */
.gallery-item {
    position: relative;
    width: calc(50% - 5px);
    cursor: pointer;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.gallery-item:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.gallery-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    display: block;
}
.gallery-item .image-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.7));
    color: #fff;
    padding: 8px;
    font-size: 11px;
    text-align: center;
}
.no-images-msg {
    color: #888;
    text-align: center;
    padding: 20px;
    width: 100%;
}
</style>

<script>
// NOTE: This constant holds the image path
const BASE_URL_FOR_JS = '<?php echo $base_image_path_relative; ?>'; 
let currentChecklistId = null;
let currentChecklistDesc = null;
let openGalleryAfterUpload = false; // Flag to reopen gallery after upload

function toggleAddOverlay(show){
  document.getElementById('addItemOverlay').style.display = show ? 'flex' : 'none';
}

// Toggle Upload Overlay
function toggleUploadOverlay(show, id = null, desc = null, fromGallery = false){
    if (show) {
        // Hide gallery first if it's open (to prevent z-index issues)
        document.getElementById('viewImageOverlay').style.display = 'none';
        openGalleryAfterUpload = fromGallery; // Remember to reopen gallery after upload
        
        document.getElementById('upload_checklist_id').value = id;
        document.getElementById('uploadTitle').textContent = `Upload Proof for: ${desc}`;
        document.getElementById('uploadImageOverlay').style.display = 'flex';
        document.getElementById('uploadError').textContent = '';
        document.getElementById('uploadImageForm').reset();
        currentChecklistId = id;
        currentChecklistDesc = desc;
    } else {
        document.getElementById('uploadImageOverlay').style.display = 'none';
    }
}

// Toggle View Images Gallery Overlay (Multiple Images)
function toggleViewOverlay(show, id = null, desc = null){
    if (show) {
        document.getElementById('viewTitle').textContent = `Proof Images: ${desc}`;
        document.getElementById('view_checklist_id').value = id;
        document.getElementById('viewImageOverlay').style.display = 'flex';
        currentChecklistId = id;
        currentChecklistDesc = desc;
        loadImages(id);
    } else {
        document.getElementById('viewImageOverlay').style.display = 'none';
        document.getElementById('imageGallery').innerHTML = '<p id="loadingImages" style="color:#888;">Loading images...</p>';
    }
}

// Load images for a checklist item
function loadImages(checklistId) {
    const gallery = document.getElementById('imageGallery');
    gallery.innerHTML = '<p style="color:#888;">Loading images...</p>';
    
    const formData = new FormData();
    formData.append('checklist_id', checklistId);
    formData.append('action', 'get_images');
    
    fetch('process_upload_checklist_image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            if (data.data.length === 0) {
                gallery.innerHTML = '<p class="no-images-msg">No images uploaded yet.</p>';
            } else {
                gallery.innerHTML = '';
                data.data.forEach(img => {
                    const imageUrl = BASE_URL_FOR_JS + encodeURIComponent(img.image_path);
                    const div = document.createElement('div');
                    div.className = 'gallery-item';
                    div.innerHTML = `
                        <img src="${imageUrl}" alt="Proof Image" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><text x=%2210%22 y=%2250%22>Error</text></svg>'">
                        <div class="image-overlay">Click to view</div>
                    `;
                    div.onclick = () => openSingleImage(img.id, imageUrl);
                    gallery.appendChild(div);
                });
            }
        } else {
            gallery.innerHTML = '<p class="no-images-msg">Failed to load images.</p>';
        }
    })
    .catch(err => {
        console.error('Error loading images:', err);
        gallery.innerHTML = '<p class="no-images-msg">Error loading images.</p>';
    });
}

// Open single image in larger view
function openSingleImage(imageId, imageUrl) {
    document.getElementById('singleProofImage').src = imageUrl;
    document.getElementById('single_image_id').value = imageId;
    document.getElementById('singleImageTitle').textContent = 'Image Preview';
    document.getElementById('singleImageOverlay').style.display = 'flex';
}

// Close single image view
function closeSingleImage() {
    document.getElementById('singleImageOverlay').style.display = 'none';
    document.getElementById('singleProofImage').src = '';
}

// Remove single image
function removeSingleImage() {
    const imageId = document.getElementById('single_image_id').value;
    const checklistId = document.getElementById('view_checklist_id').value;
    
    if (!confirm("Are you sure you want to remove this image?")) {
        return;
    }
    
    const formData = new FormData();
    formData.append('checklist_id', checklistId);
    formData.append('image_id', imageId);
    formData.append('action', 'remove');

    fetch('process_upload_checklist_image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeSingleImage();
            loadImages(checklistId); // Reload gallery
            reloadChecklist(); // Reload table to update count
        } else {
            showToast('Removal Failed: ' + data.message, 'error');
        }
    })
    .catch(err => {
        showToast('An unexpected network error occurred.', 'error');
        console.error('Removal Error:', err);
    });
}

// Remove all images for a checklist item
function removeAllImages() {
    const checklistId = document.getElementById('view_checklist_id').value;
    
    if (!confirm("Are you sure you want to remove ALL images for this item?")) {
        return;
    }
    
    const formData = new FormData();
    formData.append('checklist_id', checklistId);
    formData.append('action', 'remove_all');

    fetch('process_upload_checklist_image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            toggleViewOverlay(false);
            reloadChecklist();
        } else {
            showToast('Removal Failed: ' + data.message, 'error');
        }
    })
    .catch(err => {
        showToast('An unexpected network error occurred.', 'error');
        console.error('Removal Error:', err);
    });
}

// Reload table
function reloadChecklist() {
  const timestamp = new Date().getTime();
  fetch(`view_checklist.php?project_id=<?= $project_id; ?>&unit_id=<?= $unit_id; ?>&_ts=${timestamp} #checklistTableContainer`)
    .then(res => res.text())
    .then(html => {
      const parser = new DOMParser();
      const newDoc = parser.parseFromString(html, 'text/html');
      const newContent = newDoc.querySelector('#checklistTableContainer');
      if (newContent) {
          document.querySelector('#checklistTableContainer').innerHTML = newContent.innerHTML;
          attachEventListeners();
      } else {
          console.error("Failed to fetch new checklist content.");
      }
    })
    .catch(err => console.error('Error reloading checklist:', err));
}

// Add new item
document.getElementById('addItemForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch('process_add_checklist_item.php', { method: 'POST', body: formData })
    .then(() => {
      toggleAddOverlay(false);
      this.reset();
      reloadChecklist();
    })
    .catch(err => console.error('Error adding item:', err));
});

// Image Upload Form Submission
document.getElementById('uploadImageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    document.getElementById('uploadError').textContent = 'Uploading...';

    fetch('process_upload_checklist_image.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Image uploaded successfully!', 'success');
                toggleUploadOverlay(false);
                reloadChecklist();
                // Reopen gallery if we came from there
                if (openGalleryAfterUpload) {
                    toggleViewOverlay(true, currentChecklistId, currentChecklistDesc);
                    openGalleryAfterUpload = false;
                }
            } else {
                document.getElementById('uploadError').textContent = `❌ Upload Failed: ${data.message}`;
            }
        })
        .catch(err => {
            document.getElementById('uploadError').textContent = '❌ An unexpected network error occurred.';
            console.error('Upload error:', err);
        });
});

// Event Listeners
function attachEventListeners() {
  // Check/Uncheck Listeners
  document.querySelectorAll('.action-btn[data-action]').forEach(btn => {
    btn.onclick = () => {
      const id = btn.dataset.id;
      const action = btn.dataset.action;
      fetch(`process_toggle_checklist_item.php?id=${id}&project_id=<?= $project_id; ?>&unit_id=<?= $unit_id; ?>&action=${action}`)
        .then(() => reloadChecklist());
    };
  });

  // Delete Listeners
  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.onclick = () => {
      const id = btn.dataset.id;
      if (confirm('Delete this checklist item?')) {
        fetch(`delete_checklist_item.php?id=${id}&project_id=<?= $project_id; ?>&unit_id=<?= $unit_id; ?>`)
          .then(res => res.json())
          .then(data => {
            if (data.status === 'success') {
              reloadChecklist();
            } else {
              alert(data.message || 'Failed to delete checklist item.');
            }
          })
          .catch(() => alert('Network error. Failed to delete checklist item.'));
      }
    };
  });
  
  // Insert Image Listeners
  document.querySelectorAll('.insert-image-btn').forEach(btn => {
      btn.onclick = () => {
          const id = btn.dataset.id;
          const desc = btn.dataset.itemDesc;
          toggleUploadOverlay(true, id, desc);
      };
  });

  // View Images Listeners (Multiple images gallery)
  document.querySelectorAll('.view-images-btn').forEach(btn => {
      btn.onclick = () => {
          const id = btn.dataset.id;
          const desc = btn.dataset.itemDesc;
          toggleViewOverlay(true, id, desc);
      };
  });
  
  // Remove All Images Button
  const removeAllBtn = document.getElementById('removeAllImagesBtn');
  if (removeAllBtn) {
      removeAllBtn.onclick = removeAllImages;
  }
  
  // Add New Image Button (from gallery view)
  const addNewBtn = document.getElementById('addNewImageBtn');
  if (addNewBtn) {
      addNewBtn.onclick = () => {
          toggleUploadOverlay(true, currentChecklistId, currentChecklistDesc, true); // true = from gallery
      };
  }
  
  // Remove Single Image Button
  const removeSingleBtn = document.getElementById('removeSingleImageBtn');
  if (removeSingleBtn) {
      removeSingleBtn.onclick = removeSingleImage;
  }
}

// Run after page load
attachEventListeners();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>