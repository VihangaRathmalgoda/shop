<?php
require_once __DIR__ . '/../includes/config.php';
start_secure_session('admin');
requireAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action_type'] ?? '';
    if ($act === 'save_category') {
        $id       = intval($_POST['cat_id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $parentId = intval($_POST['parent_id'] ?? 0) ?: null;
        $sort     = intval($_POST['sort_order'] ?? 0);
        $active   = isset($_POST['is_active']) ? 1 : 0;
        $slug     = slugify($name);
        $image    = $_POST['existing_image'] ?? null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $res = uploadImage($_FILES['image'], PRODUCT_UPLOAD_PATH, 'cat');
            if (isset($res['filename'])) $image = $res['filename'];
        }

        if ($id) {
            $db->prepare("UPDATE categories SET name=?,slug=?,description=?,parent_id=?,sort_order=?,is_active=?" . ($image !== null ? ',image=?' : '') . " WHERE id=?")
               ->execute($image !== null ? [$name,$slug,$desc,$parentId,$sort,$active,$image,$id] : [$name,$slug,$desc,$parentId,$sort,$active,$id]);
        } else {
            // Ensure unique slug
            $check = $db->prepare("SELECT COUNT(*) FROM categories WHERE slug=?"); $check->execute([$slug]);
            if ($check->fetchColumn() > 0) $slug .= '-' . time();
            $db->prepare("INSERT INTO categories (name,slug,description,parent_id,sort_order,is_active,image) VALUES (?,?,?,?,?,?,?)")
               ->execute([$name,$slug,$desc,$parentId,$sort,$active,$image]);
        }
        $_SESSION['flash_success'] = 'Category saved!';
        header('Location: /shop/admin/categories.php'); exit;
    }
    if ($act === 'delete_category') {
        $id = intval($_POST['cat_id']);
        $count = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id=?"); $count->execute([$id]);
        if ($count->fetchColumn() > 0) {
            $_SESSION['flash_error'] = 'Cannot delete: products exist in this category.';
        } else {
            $db->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
            $_SESSION['flash_success'] = 'Category deleted!';
        }
        header('Location: /shop/admin/categories.php'); exit;
    }
}

$categories = $db->query("SELECT c.*, p.name as parent_name, (SELECT COUNT(*) FROM products WHERE category_id=c.id) as product_count FROM categories c LEFT JOIN categories p ON c.parent_id=p.id ORDER BY c.sort_order")->fetchAll();
$parentCats = $db->query("SELECT id,name FROM categories WHERE parent_id IS NULL AND is_active=1 ORDER BY sort_order")->fetchAll();

include __DIR__ . '/includes/admin_header.php';
?>
<div class="d-flex" id="adminWrapper">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<div class="flex-grow-1 p-4" id="adminContent">

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">Categories</h2>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" onclick="resetCatForm()"><i class="bi bi-plus-circle me-2"></i>Add Category</button>
</div>

<div class="card border-0 shadow-sm" style="border-radius:10px">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Image</th><th>Name</th><th>Parent</th><th>Products</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($categories as $cat): ?>
          <tr>
            <td>
              <?php if ($cat['image']): ?>
              <img src="<?= UPLOAD_URL ?>products/<?= htmlspecialchars($cat['image']) ?>" class="tbl-img">
              <?php else: ?>
              <div class="tbl-img bg-light d-flex align-items-center justify-content-center rounded"><i class="bi bi-grid text-muted"></i></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($cat['name']) ?></div>
              <small class="text-muted">/<?= htmlspecialchars($cat['slug']) ?></small>
            </td>
            <td><?= $cat['parent_name'] ? htmlspecialchars($cat['parent_name']) : '<span class="text-muted">—</span>' ?></td>
            <td><span class="badge bg-secondary"><?= $cat['product_count'] ?></span></td>
            <td><?= $cat['sort_order'] ?></td>
            <td><span class="badge <?= $cat['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $cat['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary py-0" onclick="editCat(<?= htmlspecialchars(json_encode($cat)) ?>)"><i class="bi bi-pencil"></i></button>
              <form method="POST" style="display:inline" id="delCat<?= $cat['id'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action_type" value="delete_category">
                <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                <button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="confirmDelete('delCat<?= $cat['id'] ?>')" <?= $cat['product_count'] > 0 ? 'disabled title="Has products"' : '' ?>><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action_type" value="save_category">
        <input type="hidden" name="cat_id" id="catModalId" value="0">
        <input type="hidden" name="existing_image" id="catExistingImg">
        <div class="modal-header">
          <h5 class="modal-title" id="catModalTitle">Add Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">Category Name *</label>
              <input type="text" name="name" id="catName" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Sort Order</label>
              <input type="number" name="sort_order" id="catSort" class="form-control" value="0">
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea name="description" id="catDesc" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Parent Category</label>
              <select name="parent_id" id="catParent" class="form-select">
                <option value="">— None (Top Level) —</option>
                <?php foreach ($parentCats as $pc): ?>
                <option value="<?= $pc['id'] ?>"><?= htmlspecialchars($pc['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Category Image</label>
              <input type="file" name="image" class="form-control" accept="image/*" onchange="previewCatImg(this)">
              <div id="catImgPreview" class="mt-2"></div>
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" id="catActive" checked>
                <label class="form-check-label" for="catActive">Active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

</div></div>
<script>
function resetCatForm() {
    document.getElementById('catModalTitle').textContent = 'Add Category';
    document.getElementById('catModalId').value = 0;
    document.getElementById('catName').value = '';
    document.getElementById('catDesc').value = '';
    document.getElementById('catParent').value = '';
    document.getElementById('catSort').value = 0;
    document.getElementById('catActive').checked = true;
    document.getElementById('catImgPreview').innerHTML = '';
    document.getElementById('catExistingImg').value = '';
}
function editCat(c) {
    document.getElementById('catModalTitle').textContent = 'Edit Category';
    document.getElementById('catModalId').value = c.id;
    document.getElementById('catName').value = c.name;
    document.getElementById('catDesc').value = c.description || '';
    document.getElementById('catParent').value = c.parent_id || '';
    document.getElementById('catSort').value = c.sort_order;
    document.getElementById('catActive').checked = c.is_active == 1;
    document.getElementById('catExistingImg').value = c.image || '';
    document.getElementById('catImgPreview').innerHTML = c.image ? `<img src="<?= UPLOAD_URL ?>products/${c.image}" style="height:60px;border-radius:6px">` : '';
    new bootstrap.Modal(document.getElementById('catModal')).show();
}
function previewCatImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { document.getElementById('catImgPreview').innerHTML = `<img src="${e.target.result}" style="height:60px;border-radius:6px">`; };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
