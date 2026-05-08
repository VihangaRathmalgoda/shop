<?php
require_once __DIR__ . '/../includes/config.php';
start_secure_session('admin');
requireAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $updates = $_POST['stock'] ?? [];
    foreach ($updates as $stockId => $qty) {
        $db->prepare("UPDATE product_stock SET quantity=? WHERE id=?")->execute([max(0,intval($qty)), intval($stockId)]);
    }
    $_SESSION['flash_success'] = 'Stock updated!';
    header('Location: /shop/admin/stock.php'); exit;
}

$filter = $_GET['filter'] ?? '';
$search = trim($_GET['q'] ?? '');
$where  = "WHERE p.status='active'";
$params = [];
if ($search) { $where .= " AND (p.name LIKE ? OR p.product_code LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filter === 'lowstock') $where .= " AND (SELECT SUM(ps2.quantity) FROM product_stock ps2 WHERE ps2.product_id=p.id) BETWEEN 1 AND 5";
if ($filter === 'oos')      $where .= " AND (SELECT SUM(ps2.quantity) FROM product_stock ps2 WHERE ps2.product_id=p.id) = 0";

$products = $db->prepare("SELECT p.id, p.name, p.product_code, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY p.name LIMIT 100");
$products->execute($params);
$products = $products->fetchAll();

include __DIR__ . '/includes/admin_header.php';
?>
<div class="d-flex" id="adminWrapper">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<div class="flex-grow-1 p-4" id="adminContent">

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">Stock Manager</h2>
  <div class="d-flex gap-2">
    <a href="?filter=lowstock" class="btn btn-sm btn-outline-warning">Low Stock</a>
    <a href="?filter=oos" class="btn btn-sm btn-outline-danger">Out of Stock</a>
    <a href="?" class="btn btn-sm btn-outline-secondary">All</a>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
  <div class="card-body py-2">
    <form class="d-flex gap-2" method="GET">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <input type="search" name="q" class="form-control form-control-sm" style="max-width:280px" placeholder="Search product name or code..." value="<?= htmlspecialchars($search) ?>">
      <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search"></i></button>
    </form>
  </div>
</div>

<form method="POST">
<?= csrf_field() ?>
<div class="card border-0 shadow-sm" style="border-radius:10px">
  <div class="card-body p-0">
    <?php foreach ($products as $p): ?>
    <?php
      $colors = $db->prepare("SELECT * FROM product_colors WHERE product_id=? ORDER BY sort_order"); $colors->execute([$p['id']]); $colors = $colors->fetchAll();
      $sizes  = $db->prepare("SELECT * FROM product_sizes WHERE product_id=? ORDER BY sort_order"); $sizes->execute([$p['id']]); $sizes = $sizes->fetchAll();
      $stockRows = $db->prepare("SELECT * FROM product_stock WHERE product_id=?"); $stockRows->execute([$p['id']]); $stockRows = $stockRows->fetchAll();
      $stockMap = [];
      foreach ($stockRows as $s) $stockMap[$s['color_id']][$s['size_id']] = $s;
      $totalStock = array_sum(array_map(fn($s) => $s['quantity'], $stockRows));
    ?>
    <div class="p-3 border-bottom">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
          <span class="fw-bold"><?= htmlspecialchars($p['name']) ?></span>
          <span class="badge bg-secondary ms-2 small"><?= htmlspecialchars($p['product_code']) ?></span>
          <span class="text-muted small ms-2"><?= htmlspecialchars($p['cat_name']) ?></span>
        </div>
        <span class="badge <?= $totalStock === 0 ? 'bg-danger' : ($totalStock <= 5 ? 'bg-warning text-dark' : 'bg-success') ?>">Total: <?= $totalStock ?></span>
      </div>

      <?php if (!empty($colors) && !empty($sizes)): ?>
      <div class="table-responsive">
        <table class="table table-bordered table-sm mb-0 small">
          <thead class="table-light">
            <tr>
              <th>Color / Size</th>
              <?php foreach ($sizes as $sz): ?><th class="text-center"><?= htmlspecialchars($sz['size_label']) ?></th><?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($colors as $col): ?>
            <tr>
              <td>
                <span class="color-preview me-1" style="width:16px;height:16px;border-radius:50%;background:<?= htmlspecialchars($col['color_hex']) ?>;display:inline-block;border:1px solid rgba(0,0,0,0.1)"></span>
                <?= htmlspecialchars($col['color_name']) ?>
              </td>
              <?php foreach ($sizes as $sz): ?>
              <?php $stockEntry = $stockMap[$col['id']][$sz['id']] ?? null; ?>
              <td class="text-center p-1">
                <?php if ($stockEntry): ?>
                <input type="number" name="stock[<?= $stockEntry['id'] ?>]" class="form-control form-control-sm text-center p-1" style="width:70px;margin:auto;<?= $stockEntry['quantity']==0?'border-color:#dc3545':($stockEntry['quantity']<=5?'border-color:#ffc107':'') ?>" value="<?= $stockEntry['quantity'] ?>" min="0">
                <?php else: ?>
                <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <p class="text-muted small mb-0">No colors/sizes configured. <a href="/shop/admin/products.php?action=edit&id=<?= $p['id'] ?>">Add them</a></p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($products)): ?><div class="text-center text-muted py-4">No products found.</div><?php endif; ?>
  </div>
</div>
<?php if (!empty($products)): ?>
<div class="mt-3 text-end">
  <button type="submit" class="btn btn-primary fw-bold px-5"><i class="bi bi-save me-2"></i>Save All Stock Changes</button>
</div>
<?php endif; ?>
</form>

</div></div>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
