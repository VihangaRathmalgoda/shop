<?php
require_once __DIR__ . '/../includes/config.php';
start_secure_session('admin');
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action_type'] ?? '';

    if ($act === 'save_banner') {
        $id       = intval($_POST['banner_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $btnText  = trim($_POST['button_text'] ?? '');
        $btnLink  = trim($_POST['button_link'] ?? '');
        $sort     = intval($_POST['sort_order'] ?? 0);
        $active   = isset($_POST['is_active']) ? 1 : 0;

        $imagePath = $_POST['existing_image'] ?? '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $res = uploadImage($_FILES['image'], BANNER_UPLOAD_PATH, 'banner');
            if (isset($res['filename'])) $imagePath = $res['filename'];
        }

        if ($id) {
            $db->prepare("UPDATE banners SET title=?,subtitle=?,button_text=?,button_link=?,sort_order=?,is_active=?" . ($imagePath ? ',image_path=?' : '') . " WHERE id=?")
               ->execute($imagePath ? [$title,$subtitle,$btnText,$btnLink,$sort,$active,$imagePath,$id] : [$title,$subtitle,$btnText,$btnLink,$sort,$active,$id]);
        } else {
            if (!$imagePath) { $_SESSION['flash_error'] = 'Image is required.'; header('Location: /shop/admin/banners.php'); exit; }
            $db->prepare("INSERT INTO banners (title,subtitle,button_text,button_link,image_path,sort_order,is_active) VALUES (?,?,?,?,?,?,?)")
               ->execute([$title,$subtitle,$btnText,$btnLink,$imagePath,$sort,$active]);
        }
        $_SESSION['flash_success'] = 'Banner saved!';
        header('Location: /shop/admin/banners.php');
        exit;
    }

    if ($act === 'delete_banner') {
        $id = intval($_POST['banner_id'] ?? 0);
        $row = $db->prepare("SELECT image_path FROM banners WHERE id=?"); $row->execute([$id]); $row = $row->fetch();
        if ($row) { @unlink(BANNER_UPLOAD_PATH . $row['image_path']); $db->prepare("DELETE FROM banners WHERE id=?")->execute([$id]); }
        $_SESSION['flash_success'] = 'Banner deleted!';
        header('Location: /shop/admin/banners.php');
        exit;
    }
}

$banners  = $db->query("SELECT * FROM banners ORDER BY sort_order ASC")->fetchAll();
$editBanner = null;
if (isset($_GET['edit'])) { $editBanner = $db->prepare("SELECT * FROM banners WHERE id=?"); $editBanner->execute([intval($_GET['edit'])]); $editBanner = $editBanner->fetch(); }

include __DIR__ . '/includes/admin_header.php';
?>
<div class="d-flex" id="adminWrapper">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<div class="flex-grow-1 p-4" id="adminContent">

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">Banners <small class="fs-6 fw-normal text-muted">(Hero Carousel)</small></h2>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bannerModal" onclick="openAddModal()"><i class="bi bi-plus-circle me-2"></i>Add Banner</button>
</div>

<div class="row g-3">
  <?php foreach ($banners as $b): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm" style="border-radius:12px;overflow:hidden">
      <div class="position-relative">
        <img src="<?= UPLOAD_URL ?>banners/<?= htmlspecialchars($b['image_path']) ?>" class="w-100" style="height:180px;object-fit:cover">
        <span class="position-absolute top-0 end-0 m-2 badge <?= $b['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $b['is_active'] ? 'Active' : 'Inactive' ?></span>
        <span class="position-absolute top-0 start-0 m-2 badge bg-dark">Order: <?= $b['sort_order'] ?></span>
      </div>
      <div class="card-body">
        <h6 class="fw-bold"><?= htmlspecialchars($b['title'] ?: '(No title)') ?></h6>
        <p class="text-muted small mb-2"><?= htmlspecialchars($b['subtitle'] ?: '—') ?></p>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= htmlspecialchars(json_encode($b)) ?>)"><i class="bi bi-pencil me-1"></i>Edit</button>
          <form method="POST" style="display:inline" id="delBanner<?= $b['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="delete_banner">
            <input type="hidden" name="banner_id" value="<?= $b['id'] ?>">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete('delBanner<?= $b['id'] ?>')"><i class="bi bi-trash"></i></button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($banners)): ?>
  <div class="col-12"><div class="alert alert-info">No banners yet. Add your first carousel banner!</div></div>
  <?php endif; ?>
</div>

<!-- Banner Modal -->
<div class="modal fade" id="bannerModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action_type" value="save_banner">
        <input type="hidden" name="banner_id" id="modalBannerId" value="0">
        <input type="hidden" name="existing_image" id="modalExistingImg">
        <div class="modal-header">
          <h5 class="modal-title" id="bannerModalTitle">Add Banner</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Banner Image <span id="imgRequired">*</span></label>
              <input type="file" name="image" id="bannerImageInput" class="form-control" accept="image/*" onchange="previewBanner(this)">
              <small class="text-muted">Recommended: 1920×600px or 16:5 ratio. Max 5MB.</small>
              <div id="bannerPreview" class="mt-2" style="display:none">
                <img id="bannerPreviewImg" style="max-height:150px;border-radius:8px;max-width:100%">
              </div>
              <div id="existingPreview" class="mt-2"></div>
            </div>
            <div class="col-md-8">
              <label class="form-label">Title</label>
              <input type="text" name="title" id="modalTitle" class="form-control" placeholder="e.g. New Season Sale">
            </div>
            <div class="col-md-4">
              <label class="form-label">Sort Order</label>
              <input type="number" name="sort_order" id="modalSort" class="form-control" value="0">
            </div>
            <div class="col-12">
              <label class="form-label">Subtitle / Description</label>
              <textarea name="subtitle" id="modalSubtitle" class="form-control" rows="2" placeholder="e.g. Up to 50% off on selected items"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Button Text</label>
              <input type="text" name="button_text" id="modalBtnText" class="form-control" placeholder="Shop Now">
            </div>
            <div class="col-md-6">
              <label class="form-label">Button Link</label>
              <input type="text" name="button_link" id="modalBtnLink" class="form-control" placeholder="/shop/shop.php">
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" id="modalActive" checked>
                <label class="form-check-label" for="modalActive">Active (show on website)</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Banner</button>
        </div>
      </form>
    </div>
  </div>
</div>

</div></div>

<script>
function previewBanner(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { document.getElementById('bannerPreviewImg').src = e.target.result; document.getElementById('bannerPreview').style.display='block'; };
        reader.readAsDataURL(input.files[0]);
    }
}
function openAddModal() {
    document.getElementById('bannerModalTitle').textContent = 'Add Banner';
    document.getElementById('modalBannerId').value = 0;
    document.getElementById('modalExistingImg').value = '';
    document.getElementById('modalTitle').value = '';
    document.getElementById('modalSubtitle').value = '';
    document.getElementById('modalBtnText').value = '';
    document.getElementById('modalBtnLink').value = '';
    document.getElementById('modalSort').value = 0;
    document.getElementById('modalActive').checked = true;
    document.getElementById('bannerPreview').style.display = 'none';
    document.getElementById('existingPreview').innerHTML = '';
    document.getElementById('imgRequired').textContent = '*';
}
function openEditModal(b) {
    document.getElementById('bannerModalTitle').textContent = 'Edit Banner';
    document.getElementById('modalBannerId').value = b.id;
    document.getElementById('modalExistingImg').value = b.image_path;
    document.getElementById('modalTitle').value = b.title || '';
    document.getElementById('modalSubtitle').value = b.subtitle || '';
    document.getElementById('modalBtnText').value = b.button_text || '';
    document.getElementById('modalBtnLink').value = b.button_link || '';
    document.getElementById('modalSort').value = b.sort_order;
    document.getElementById('modalActive').checked = b.is_active == 1;
    document.getElementById('bannerPreview').style.display = 'none';
    document.getElementById('existingPreview').innerHTML = `<img src="<?= UPLOAD_URL ?>banners/${b.image_path}" style="max-height:100px;border-radius:6px">`;
    document.getElementById('imgRequired').textContent = '(optional: only upload to replace)';
    new bootstrap.Modal(document.getElementById('bannerModal')).show();
}
</script>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
