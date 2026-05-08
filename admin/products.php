<?php
require_once __DIR__ . '/../includes/config.php';
start_secure_session('admin');
requireAdmin();

$db = getDB();
$action  = $_GET['action'] ?? 'list';
$editId  = intval($_GET['id'] ?? 0);
$message = '';
$error   = '';

// ===================== HANDLE POST =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action_type'] ?? '';

    if ($act === 'save_product') {
        $name       = trim($_POST['name'] ?? '');
        $code       = trim($_POST['product_code'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $catId      = intval($_POST['category_id'] ?? 0);
        $basePrice  = floatval($_POST['base_price'] ?? 0);
        $salePrice  = $_POST['sale_price'] !== '' ? floatval($_POST['sale_price']) : null;
        $isOnSale   = isset($_POST['is_on_sale']) ? 1 : 0;
        $discPct    = intval($_POST['discount_percent'] ?? 0);
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $isNew      = isset($_POST['is_new_arrival']) ? 1 : 0;
        $status     = $_POST['status'] ?? 'active';
        $slug       = slugify($name) . '-' . time();
        $pid        = intval($_POST['product_id'] ?? 0);

        try {
            if ($pid) {
                // Update
                $stmt = $db->prepare("UPDATE products SET name=?,product_code=?,description=?,category_id=?,base_price=?,sale_price=?,is_on_sale=?,discount_percent=?,is_featured=?,is_new_arrival=?,status=?,updated_at=NOW() WHERE id=?");
                $stmt->execute([$name,$code,$desc,$catId,$basePrice,$salePrice,$isOnSale,$discPct,$isFeatured,$isNew,$status,$pid]);
                $productId = $pid;
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO products (name,slug,product_code,description,category_id,base_price,sale_price,is_on_sale,discount_percent,is_featured,is_new_arrival,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$name,$slug,$code,$desc,$catId,$basePrice,$salePrice,$isOnSale,$discPct,$isFeatured,$isNew,$status]);
                $productId = $db->lastInsertId();
            }

            // Colors
            $colorNames = $_POST['color_names'] ?? [];
            $colorHexes = $_POST['color_hexes'] ?? [];
            if ($pid) $db->prepare("DELETE FROM product_colors WHERE product_id=?")->execute([$productId]);
            $colorIds = [];
            foreach ($colorNames as $i => $cName) {
                if (!$cName) continue;
                $stmt = $db->prepare("INSERT INTO product_colors (product_id,color_name,color_hex,sort_order) VALUES (?,?,?,?)");
                $stmt->execute([$productId, $cName, $colorHexes[$i] ?? '#000000', $i]);
                $colorIds[$i] = $db->lastInsertId();
            }

            // Sizes
            $sizeLabels = $_POST['size_labels'] ?? [];
            if ($pid) $db->prepare("DELETE FROM product_sizes WHERE product_id=?")->execute([$productId]);
            $sizeIds = [];
            foreach ($sizeLabels as $i => $sLabel) {
                if (!$sLabel) continue;
                $stmt = $db->prepare("INSERT INTO product_sizes (product_id,size_label,sort_order) VALUES (?,?,?)");
                $stmt->execute([$productId, $sLabel, $i]);
                $sizeIds[$i] = $db->lastInsertId();
            }

            // Stock
            if ($pid) $db->prepare("DELETE FROM product_stock WHERE product_id=?")->execute([$productId]);
            $stockData = $_POST['stock'] ?? [];
            foreach ($stockData as $ci => $sizes) {
                if (!isset($colorIds[$ci])) continue;
                foreach ($sizes as $si => $qty) {
                    if (!isset($sizeIds[$si])) continue;
                    $stmt = $db->prepare("INSERT INTO product_stock (product_id,color_id,size_id,quantity) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)");
                    $stmt->execute([$productId, $colorIds[$ci], $sizeIds[$si], intval($qty)]);
                }
            }

            // Images per color
            foreach ($colorIds as $ci => $colorId) {
                $fileKey = "color_images_{$ci}";
                if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'][0] !== UPLOAD_ERR_NO_FILE) {
                    $files = $_FILES[$fileKey];
                    for ($j = 0; $j < count($files['name']); $j++) {
                        if ($files['error'][$j] !== UPLOAD_ERR_OK) continue;
                        $file = ['name'=>$files['name'][$j],'type'=>$files['type'][$j],'tmp_name'=>$files['tmp_name'][$j],'error'=>$files['error'][$j],'size'=>$files['size'][$j]];
                        $res = uploadImage($file, PRODUCT_UPLOAD_PATH, 'prod');
                        if (isset($res['filename'])) {
                            $isPrimary = ($ci === 0 && $j === 0) ? 1 : 0;
                            $db->prepare("INSERT INTO product_images (product_id,color_id,image_path,is_primary,sort_order) VALUES (?,?,?,?,?)")->execute([$productId,$colorId,$res['filename'],$isPrimary,$j]);
                        }
                    }
                }
            }
            // General images (no color)
            if (isset($_FILES['general_images']) && $_FILES['general_images']['error'][0] !== UPLOAD_ERR_NO_FILE) {
                $files = $_FILES['general_images'];
                for ($j = 0; $j < count($files['name']); $j++) {
                    if ($files['error'][$j] !== UPLOAD_ERR_OK) continue;
                    $file = ['name'=>$files['name'][$j],'type'=>$files['type'][$j],'tmp_name'=>$files['tmp_name'][$j],'error'=>$files['error'][$j],'size'=>$files['size'][$j]];
                    $res = uploadImage($file, PRODUCT_UPLOAD_PATH, 'prod');
                    if (isset($res['filename'])) {
                        $db->prepare("INSERT INTO product_images (product_id,color_id,image_path,is_primary,sort_order) VALUES (?,NULL,?,?,?)")->execute([$productId,$res['filename'],0,$j]);
                    }
                }
            }

            $_SESSION['flash_success'] = $pid ? 'Product updated successfully!' : 'Product added successfully!';
            header('Location: /shop/admin/products.php');
            exit;
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }

    if ($act === 'delete_product') {
        $pid = intval($_POST['product_id'] ?? 0);
        $db->prepare("UPDATE products SET status='inactive' WHERE id=?")->execute([$pid]);
        $_SESSION['flash_success'] = 'Product deactivated.';
        header('Location: /shop/admin/products.php');
        exit;
    }

    if ($act === 'delete_image') {
        $imgId = intval($_POST['image_id'] ?? 0);
        $imgRow = $db->prepare("SELECT image_path FROM product_images WHERE id=?");
        $imgRow->execute([$imgId]);
        $imgRow = $imgRow->fetch();
        if ($imgRow) {
            @unlink(PRODUCT_UPLOAD_PATH . $imgRow['image_path']);
            $db->prepare("DELETE FROM product_images WHERE id=?")->execute([$imgId]);
        }
        echo json_encode(['success'=>true]);
        exit;
    }
}

// ===================== FETCH DATA =====================
$categories = $db->query("SELECT id,name FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();

$filter   = $_GET['filter'] ?? '';
$search   = trim($_GET['q'] ?? '');
$where    = "WHERE p.status!='inactive'";
$params   = [];
if ($search) { $where .= " AND (p.name LIKE ? OR p.product_code LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filter === 'featured') $where .= " AND p.is_featured=1";
if ($filter === 'sale') $where .= " AND p.is_on_sale=1";
if ($filter === 'lowstock') $where .= " AND (SELECT SUM(ps.quantity) FROM product_stock ps WHERE ps.product_id=p.id) BETWEEN 1 AND 5";
if ($filter === 'oos') $where .= " AND (SELECT SUM(ps.quantity) FROM product_stock ps WHERE ps.product_id=p.id) = 0";

$products = [];
if ($action === 'list') {
    $sql = "SELECT p.*, c.name as cat_name,
        (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) as primary_image,
        (SELECT SUM(ps.quantity) FROM product_stock ps WHERE ps.product_id=p.id) as total_stock
        FROM products p LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY p.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
}

// Load product for editing
$editProduct = null;
$editColors  = [];
$editSizes   = [];
$editStockMap = [];
$editImages  = [];
if ($action === 'edit' && $editId) {
    $editProduct = $db->prepare("SELECT * FROM products WHERE id=?");
    $editProduct->execute([$editId]);
    $editProduct = $editProduct->fetch();
    $editColors = $db->prepare("SELECT * FROM product_colors WHERE product_id=? ORDER BY sort_order");
    $editColors->execute([$editId]);
    $editColors = $editColors->fetchAll();
    $editSizes = $db->prepare("SELECT * FROM product_sizes WHERE product_id=? ORDER BY sort_order");
    $editSizes->execute([$editId]);
    $editSizes = $editSizes->fetchAll();
    $stockStmt = $db->prepare("SELECT color_id,size_id,quantity FROM product_stock WHERE product_id=?");
    $stockStmt->execute([$editId]);
    foreach ($stockStmt->fetchAll() as $s) {
        $editStockMap[$s['color_id']][$s['size_id']] = $s['quantity'];
    }
    $imgStmt = $db->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY is_primary DESC, sort_order");
    $imgStmt->execute([$editId]);
    $editImages = $imgStmt->fetchAll();
}

include __DIR__ . '/includes/admin_header.php';
?>
<div class="d-flex" id="adminWrapper">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<div class="flex-grow-1 p-4" id="adminContent">

<?php if ($action === 'list'): ?>
<!-- ==================== LIST ==================== -->
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">Products</h2>
  <a href="/shop/admin/products.php?action=add" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Add Product</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
  <div class="card-body py-2">
    <form class="d-flex flex-wrap gap-2 align-items-center" method="GET">
      <input type="hidden" name="action" value="list">
      <input type="search" name="q" class="form-control form-control-sm" style="max-width:220px" placeholder="Search name or code..." value="<?= htmlspecialchars($search) ?>">
      <select name="filter" class="form-select form-select-sm" style="max-width:160px" onchange="this.form.submit()">
        <option value="">All Products</option>
        <option value="featured" <?= $filter==='featured'?'selected':'' ?>>Featured</option>
        <option value="sale" <?= $filter==='sale'?'selected':'' ?>>On Sale</option>
        <option value="lowstock" <?= $filter==='lowstock'?'selected':'' ?>>Low Stock</option>
        <option value="oos" <?= $filter==='oos'?'selected':'' ?>>Out of Stock</option>
      </select>
      <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search"></i></button>
      <a href="/shop/admin/products.php" class="btn btn-sm btn-outline-secondary">Reset</a>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:10px">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr><th>Image</th><th>Code</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
          <tr>
            <td><img src="<?= $p['primary_image'] ? UPLOAD_URL.'products/'.htmlspecialchars($p['primary_image']) : SITE_URL.'/assets/images/placeholder.png' ?>" class="tbl-img"></td>
            <td class="fw-mono small"><?= htmlspecialchars($p['product_code']) ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
              <?php if ($p['is_featured']): ?><span class="badge bg-warning text-dark small">Featured</span><?php endif; ?>
              <?php if ($p['is_on_sale']): ?><span class="badge bg-danger small">Sale</span><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
            <td>
              <?php if ($p['is_on_sale'] && $p['sale_price']): ?>
              <span class="text-success fw-bold">Rs. <?= number_format($p['sale_price'],2) ?></span><br>
              <small class="text-muted text-decoration-line-through">Rs. <?= number_format($p['base_price'],2) ?></small>
              <?php else: ?>
              Rs. <?= number_format($p['base_price'],2) ?>
              <?php endif; ?>
            </td>
            <td>
              <?php $stk = intval($p['total_stock'] ?? 0); ?>
              <span class="badge <?= $stk === 0 ? 'bg-danger' : ($stk <= 5 ? 'bg-warning text-dark' : 'bg-success') ?>"><?= $stk ?> pcs</span>
            </td>
            <td><span class="badge <?= $p['status']==='active'?'bg-success':($p['status']==='out_of_stock'?'bg-warning text-dark':'bg-secondary') ?>"><?= ucfirst($p['status']) ?></span></td>
            <td>
              <a href="/shop/admin/products.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-pencil"></i></a>
              <a href="/shop/product.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a>
              <form method="POST" style="display:inline" id="del<?= $p['id'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action_type" value="delete_product">
                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                <button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="confirmDelete('del<?= $p['id'] ?>')"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($products)): ?><tr><td colspan="8" class="text-center text-muted py-4">No products found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
<!-- ==================== ADD / EDIT FORM ==================== -->
<div class="d-flex align-items-center mb-4 gap-3">
  <a href="/shop/admin/products.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
  <h2 class="fw-bold mb-0"><?= $action==='edit' ? 'Edit Product' : 'Add New Product' ?></h2>
</div>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="productForm">
  <?= csrf_field() ?>
  <input type="hidden" name="action_type" value="save_product">
  <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?? 0 ?>">

  <div class="row g-3">
    <!-- Left column -->
    <div class="col-lg-8">
      <!-- Basic Info -->
      <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
        <div class="card-header bg-white fw-bold">Basic Information</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Product Name *</label>
              <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Product Code *</label>
              <input type="text" name="product_code" class="form-control" required value="<?= htmlspecialchars($editProduct['product_code'] ?? 'PRD-'.strtoupper(substr(uniqid(),0,6))) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Category</label>
              <select name="category_id" class="form-select">
                <option value="">— Select Category —</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($editProduct['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="active" <?= ($editProduct['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($editProduct['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="out_of_stock" <?= ($editProduct['status'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
              </select>
            </div>
            <div class="col-md-3 d-flex flex-column gap-2 justify-content-end">
              <div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" id="isFeatured" <?= ($editProduct['is_featured'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="isFeatured">Featured</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="is_new_arrival" id="isNew" <?= ($editProduct['is_new_arrival'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="isNew">New Arrival</label></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pricing -->
      <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
        <div class="card-header bg-white fw-bold">Pricing</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Base Price (Rs.) *</label>
              <input type="number" name="base_price" class="form-control" step="0.01" required value="<?= $editProduct['base_price'] ?? '' ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Sale Price (Rs.)</label>
              <input type="number" name="sale_price" class="form-control" step="0.01" id="salePriceInput" value="<?= $editProduct['sale_price'] ?? '' ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Discount %</label>
              <input type="number" name="discount_percent" class="form-control" min="0" max="100" value="<?= $editProduct['discount_percent'] ?? 0 ?>">
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_on_sale" id="isOnSale" <?= ($editProduct['is_on_sale'] ?? 0) ? 'checked' : '' ?>>
                <label class="form-check-label" for="isOnSale">Mark as On Sale</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Colors, Sizes, Stock -->
      <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
          Colors & Sizes
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addColor()"><i class="bi bi-plus"></i> Add Color</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addSize()"><i class="bi bi-plus"></i> Add Size</button>
          </div>
        </div>
        <div class="card-body">
          <!-- Sizes Row -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Sizes</label>
            <div id="sizeList" class="d-flex flex-wrap gap-2">
              <?php foreach ($editSizes as $si => $sz): ?>
              <div class="input-group input-group-sm" style="width:auto">
                <input type="text" name="size_labels[]" class="form-control" value="<?= htmlspecialchars($sz['size_label']) ?>" style="max-width:80px" placeholder="e.g. S, M, XL">
                <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove(); rebuildStock()"><i class="bi bi-x"></i></button>
              </div>
              <?php endforeach; ?>
              <?php if (empty($editSizes)): ?>
              <div class="input-group input-group-sm" style="width:auto">
                <input type="text" name="size_labels[]" class="form-control" style="max-width:80px" placeholder="e.g. S">
                <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove(); rebuildStock()"><i class="bi bi-x"></i></button>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Colors + Images + Stock -->
          <div id="colorList">
            <?php foreach ($editColors as $ci => $c): ?>
            <div class="color-row border rounded p-3 mb-3" data-ci="<?= $ci ?>">
              <div class="row g-2 align-items-center mb-2">
                <div class="col-auto"><span class="color-preview" style="background:<?= htmlspecialchars($c['color_hex']) ?>"></span></div>
                <div class="col"><input type="text" name="color_names[]" class="form-control form-control-sm" value="<?= htmlspecialchars($c['color_name']) ?>" placeholder="Color Name" onchange="rebuildStock()"></div>
                <div class="col-auto"><input type="color" name="color_hexes[]" class="form-control form-control-color form-control-sm" value="<?= htmlspecialchars($c['color_hex']) ?>" oninput="this.previousElementSibling.previousElementSibling.style.background=this.value"></div>
                <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.color-row').remove(); rebuildStock()"><i class="bi bi-trash"></i></button></div>
              </div>
              <!-- Images for this color -->
              <div class="mb-2">
                <label class="small fw-semibold">Images for this color</label>
                <input type="file" name="color_images_<?= $ci ?>[]" class="form-control form-control-sm" multiple accept="image/*">
                <!-- Existing images -->
                <?php $cImgs = array_filter($editImages, fn($img) => $img['color_id'] == $c['id']); ?>
                <?php if ($cImgs): ?>
                <div class="d-flex flex-wrap gap-1 mt-1">
                  <?php foreach ($cImgs as $img): ?>
                  <div class="position-relative">
                    <img src="<?= UPLOAD_URL ?>products/<?= htmlspecialchars($img['image_path']) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:4px">
                    <button type="button" class="btn btn-xs position-absolute top-0 end-0 btn-danger py-0 px-1" style="font-size:.65rem" onclick="deleteImage(<?= $img['id'] ?>, this)">×</button>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>
              <!-- Stock per size -->
              <div class="stock-grid" id="stockGrid_<?= $ci ?>"></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($editColors)): ?>
            <div class="color-row border rounded p-3 mb-3" data-ci="0">
              <div class="row g-2 align-items-center mb-2">
                <div class="col-auto"><span class="color-preview" style="background:#000"></span></div>
                <div class="col"><input type="text" name="color_names[]" class="form-control form-control-sm" placeholder="e.g. Black" onchange="rebuildStock()"></div>
                <div class="col-auto"><input type="color" name="color_hexes[]" class="form-control form-control-color form-control-sm" value="#000000" oninput="this.previousElementSibling.previousElementSibling.style.background=this.value"></div>
                <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.color-row').remove(); rebuildStock()"><i class="bi bi-trash"></i></button></div>
              </div>
              <div class="mb-2">
                <label class="small fw-semibold">Images for this color</label>
                <input type="file" name="color_images_0[]" class="form-control form-control-sm" multiple accept="image/*">
              </div>
              <div class="stock-grid" id="stockGrid_0"></div>
            </div>
            <?php endif; ?>
          </div>

          <!-- General images -->
          <div class="mt-3">
            <label class="form-label fw-semibold">General Product Images (no specific color)</label>
            <input type="file" name="general_images[]" class="form-control" multiple accept="image/*">
            <?php $genImgs = array_filter($editImages, fn($img) => !$img['color_id']); ?>
            <?php if ($genImgs): ?>
            <div class="d-flex flex-wrap gap-2 mt-2">
              <?php foreach ($genImgs as $img): ?>
              <div class="position-relative">
                <img src="<?= UPLOAD_URL ?>products/<?= htmlspecialchars($img['image_path']) ?>" style="width:70px;height:70px;object-fit:cover;border-radius:6px">
                <button type="button" class="btn position-absolute top-0 end-0 btn-danger py-0 px-1" style="font-size:.65rem" onclick="deleteImage(<?= $img['id'] ?>, this)">×</button>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Right sidebar -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm mb-3 sticky-top" style="top:80px; border-radius:10px">
        <div class="card-body">
          <button type="submit" class="btn btn-primary w-100 mb-2 fw-bold"><i class="bi bi-save me-2"></i><?= $action==='edit' ? 'Update Product' : 'Save Product' ?></button>
          <a href="/shop/admin/products.php" class="btn btn-outline-secondary w-100">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
const existingStock = <?= json_encode($editStockMap) ?>;
let colorIdx = <?= count($editColors) ?: 1 ?>;

function addColor() {
    const ci = colorIdx++;
    const div = document.createElement('div');
    div.className = 'color-row border rounded p-3 mb-3';
    div.dataset.ci = ci;
    div.innerHTML = `
      <div class="row g-2 align-items-center mb-2">
        <div class="col-auto"><span class="color-preview" style="background:#000"></span></div>
        <div class="col"><input type="text" name="color_names[]" class="form-control form-control-sm" placeholder="e.g. Red" onchange="rebuildStock()"></div>
        <div class="col-auto"><input type="color" name="color_hexes[]" class="form-control form-control-color form-control-sm" value="#ff0000" oninput="this.previousElementSibling.previousElementSibling.style.background=this.value"></div>
        <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.color-row').remove(); rebuildStock()"><i class="bi bi-trash"></i></button></div>
      </div>
      <div class="mb-2">
        <label class="small fw-semibold">Images for this color</label>
        <input type="file" name="color_images_${ci}[]" class="form-control form-control-sm" multiple accept="image/*">
      </div>
      <div class="stock-grid" id="stockGrid_${ci}"></div>`;
    document.getElementById('colorList').appendChild(div);
    rebuildStock();
}

function addSize() {
    const div = document.createElement('div');
    div.className = 'input-group input-group-sm';
    div.style.width = 'auto';
    div.innerHTML = `<input type="text" name="size_labels[]" class="form-control" style="max-width:80px" placeholder="e.g. XL" oninput="rebuildStock()">
      <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove(); rebuildStock()"><i class="bi bi-x"></i></button>`;
    document.getElementById('sizeList').appendChild(div);
    rebuildStock();
}

function rebuildStock() {
    const colorRows = document.querySelectorAll('.color-row');
    const sizeInputs = document.querySelectorAll('#sizeList input[name="size_labels[]"]');
    const sizes = Array.from(sizeInputs).map(i => i.value.trim()).filter(Boolean);

    colorRows.forEach((row, ci) => {
        const grid = row.querySelector('.stock-grid');
        if (!grid) return;
        if (!sizes.length) { grid.innerHTML = ''; return; }
        let html = '<label class="small fw-semibold mb-1">Stock per size</label><div class="d-flex flex-wrap gap-2">';
        sizes.forEach((size, si) => {
            html += `<div class="text-center"><small class="d-block text-muted">${size}</small><input type="number" name="stock[${ci}][${si}]" class="form-control form-control-sm text-center" style="width:60px" value="0" min="0"></div>`;
        });
        html += '</div>';
        grid.innerHTML = html;
    });
}

const CSRF_TOKEN = '<?= csrf_token() ?>';
function deleteImage(imageId, btn) {
    if (!confirm('Delete this image?')) return;
    fetch('/shop/admin/products.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN},
        body: `action_type=delete_image&image_id=${imageId}&_csrf=${encodeURIComponent(CSRF_TOKEN)}`
    }).then(r => r.json()).then(d => {
        if (d.success) btn.closest('div.position-relative').remove();
    });
}

// Init stock grid on load
document.addEventListener('DOMContentLoaded', rebuildStock);
</script>
<?php endif; ?>

</div><!-- adminContent -->
</div><!-- adminWrapper -->
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
