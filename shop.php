<?php
require_once __DIR__ . '/includes/config.php';
start_secure_session('customer');
require_once __DIR__ . '/includes/theme.php';

$db = getDB();
$settings = getSettings();

$search     = trim($_GET['q'] ?? '');
$catSlug    = trim($_GET['cat'] ?? '');
$filter     = $_GET['filter'] ?? '';
$sort       = $_GET['sort'] ?? 'newest';
$minPrice   = floatval($_GET['min_price'] ?? 0);
$maxPrice   = floatval($_GET['max_price'] ?? 99999);
$page       = max(1, intval($_GET['page'] ?? 1));
$perPage    = 16;
$offset     = ($page - 1) * $perPage;

$where  = "WHERE p.status='active'";
$params = [];

if ($search) {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.product_code LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($catSlug) {
    $catRow = $db->prepare("SELECT id FROM categories WHERE slug=?"); $catRow->execute([$catSlug]); $catRow = $catRow->fetch();
    if ($catRow) { $where .= " AND p.category_id=?"; $params[] = $catRow['id']; }
}
if ($filter === 'featured')  $where .= " AND p.is_featured=1";
if ($filter === 'new')       $where .= " AND p.is_new_arrival=1";
if ($filter === 'sale')      $where .= " AND p.is_on_sale=1";
$where .= " AND p.base_price >= ? AND p.base_price <= ?";
$params[] = $minPrice; $params[] = $maxPrice;

$orderBy = match($sort) {
    'price_asc'  => 'p.base_price ASC',
    'price_desc' => 'p.base_price DESC',
    'name'       => 'p.name ASC',
    default      => 'p.created_at DESC'
};

// Count total
$countSql = "SELECT COUNT(DISTINCT p.id) FROM products p LEFT JOIN categories c ON p.category_id=c.id $where";
$countStmt = $db->prepare($countSql); $countStmt->execute($params); $totalItems = intval($countStmt->fetchColumn());
$totalPages = max(1, ceil($totalItems / $perPage));

// Fetch products
$sql = "SELECT p.*, c.name as cat_name, c.slug as cat_slug,
  (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) as primary_image,
  (SELECT GROUP_CONCAT(color_hex ORDER BY sort_order SEPARATOR '|') FROM product_colors WHERE product_id=p.id) as color_hexes,
  (SELECT GROUP_CONCAT(color_name ORDER BY sort_order SEPARATOR '|') FROM product_colors WHERE product_id=p.id) as color_names,
  (SELECT SUM(quantity) FROM product_stock WHERE product_id=p.id) as total_stock
  FROM products p LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($sql); $stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $db->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id=c.id AND status='active') as pcount FROM categories c WHERE c.is_active=1 AND c.parent_id IS NULL ORDER BY c.sort_order")->fetchAll();

$pageTitle = $search ? "Search: $search" : ($catSlug ? ucfirst($catSlug) : 'All Products');

renderHead($pageTitle);
renderNavbar();
?>

<div class="container my-4">
  <!-- Page Header -->
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h2 class="fw-bold mb-0"><?= htmlspecialchars($pageTitle) ?></h2>
      <small class="text-muted"><?= number_format($totalItems) ?> products found</small>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <!-- Sort -->
      <select class="form-select form-select-sm" style="width:auto" onchange="applyFilter('sort',this.value)">
        <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest First</option>
        <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Price: Low to High</option>
        <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High to Low</option>
        <option value="name" <?= $sort==='name'?'selected':'' ?>>Name A-Z</option>
      </select>
      <!-- Filter pills -->
      <a href="?<?= http_build_query(array_merge($_GET,['filter'=>''])) ?>" class="btn btn-sm <?= !$filter?'btn-primary':'btn-outline-secondary' ?>">All</a>
      <a href="?<?= http_build_query(array_merge($_GET,['filter'=>'sale'])) ?>" class="btn btn-sm <?= $filter==='sale'?'btn-danger':'btn-outline-danger' ?>">Sale</a>
      <a href="?<?= http_build_query(array_merge($_GET,['filter'=>'new'])) ?>" class="btn btn-sm <?= $filter==='new'?'btn-warning text-dark':'btn-outline-warning' ?>">New</a>
      <a href="?<?= http_build_query(array_merge($_GET,['filter'=>'featured'])) ?>" class="btn btn-sm <?= $filter==='featured'?'btn-success':'btn-outline-success' ?>">Featured</a>
    </div>
  </div>

  <div class="row g-3">
    <!-- Sidebar Filters -->
    <div class="col-lg-2 d-none d-lg-block">
      <!-- Categories -->
      <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
        <div class="card-header bg-white fw-bold small py-2">Categories</div>
        <div class="list-group list-group-flush" style="border-radius:0 0 10px 10px">
          <a href="/shop/shop.php?<?= $search?'q='.urlencode($search):'' ?>" class="list-group-item list-group-item-action small <?= !$catSlug?'active':'' ?>">All Categories</a>
          <?php foreach ($categories as $cat): ?>
          <a href="/shop/shop.php?cat=<?= $cat['slug'] ?>" class="list-group-item list-group-item-action small d-flex justify-content-between <?= $catSlug===$cat['slug']?'active':'' ?>">
            <?= htmlspecialchars($cat['name']) ?>
            <span class="badge bg-secondary rounded-pill"><?= $cat['pcount'] ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Price Filter -->
      <div class="card border-0 shadow-sm" style="border-radius:10px">
        <div class="card-header bg-white fw-bold small py-2">Price Range</div>
        <div class="card-body p-3">
          <form method="GET" id="priceForm">
            <?php foreach ($_GET as $k => $v): ?>
            <?php if ($k !== 'min_price' && $k !== 'max_price'): ?><input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>"><?php endif; ?>
            <?php endforeach; ?>
            <div class="mb-2">
              <label class="form-label small">Min (Rs.)</label>
              <input type="number" name="min_price" class="form-control form-control-sm" value="<?= $minPrice ?>">
            </div>
            <div class="mb-2">
              <label class="form-label small">Max (Rs.)</label>
              <input type="number" name="max_price" class="form-control form-control-sm" value="<?= $maxPrice < 99999 ? $maxPrice : '' ?>" placeholder="Any">
            </div>
            <button type="submit" class="btn btn-sm btn-primary w-100">Apply</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Products Grid -->
    <div class="col-lg-10">
      <!-- Mobile category pills -->
      <div class="d-flex gap-2 flex-nowrap overflow-auto pb-2 mb-3 d-lg-none">
        <a href="/shop/shop.php" class="btn btn-sm <?= !$catSlug?'btn-primary':'btn-outline-secondary' ?> flex-shrink-0">All</a>
        <?php foreach ($categories as $cat): ?>
        <a href="/shop/shop.php?cat=<?= $cat['slug'] ?>" class="btn btn-sm <?= $catSlug===$cat['slug']?'btn-primary':'btn-outline-secondary' ?> flex-shrink-0"><?= htmlspecialchars($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>

      <?php if (empty($products)): ?>
      <div class="text-center py-5">
        <i class="bi bi-search" style="font-size:3rem;color:var(--primary)"></i>
        <h5 class="mt-3">No products found</h5>
        <p class="text-muted">Try adjusting your filters or search term.</p>
        <a href="/shop/shop.php" class="btn btn-primary">Clear Filters</a>
      </div>
      <?php else: ?>
      <div class="row g-3">
        <?php
        foreach ($products as $p):
            $siteUrl   = SITE_URL;
            $uploadUrl = UPLOAD_URL;
            $img       = $p['primary_image'] ? $uploadUrl . 'products/' . $p['primary_image'] : $siteUrl . '/assets/images/placeholder.png';
            $price     = number_format($p['base_price'], 2);
            $salePrice = $p['sale_price'] ? number_format($p['sale_price'], 2) : null;
            $currency  = $settings['currency_symbol'] ?? 'Rs.';
            $inStock   = ($p['total_stock'] ?? 0) > 0;
            $waNum     = preg_replace('/[^0-9]/', '', $settings['contact_whatsapp'] ?? '');
            $waEnabled = $settings['whatsapp_orders_enabled'] ?? '1';
            $portalEnabled = $settings['portal_orders_enabled'] ?? '1';
            $colors = [];
            if (!empty($p['color_hexes'])) {
                $hexes = explode('|', $p['color_hexes']);
                $names = !empty($p['color_names']) ? explode('|', $p['color_names']) : $hexes;
                foreach ($hexes as $i => $hex) $colors[] = ['hex'=>$hex,'name'=>$names[$i]??$hex];
            }
            $badgeSale = $p['is_on_sale'] ? '<span class="badge-sale">SALE</span>' : '';
            $badgeNew  = $p['is_new_arrival'] ? '<span class="badge-new">NEW</span>' : '';
            $oosOverlay = !$inStock ? '<div class="badge-oos">OUT OF STOCK</div>' : '';
            $priceHtml = $salePrice ? "<span class='price'>{$currency} {$salePrice}</span> <span class='price-original'>{$currency} {$price}</span>" : "<span class='price'>{$currency} {$price}</span>";
            $colorDotsHtml = '';
            foreach ($colors as $c) $colorDotsHtml .= "<span class='color-dot' style='background:{$c['hex']}' title='{$c['name']}'></span>";
            $waMsg = urlencode("Hi! I'm interested in: {$p['name']} (Code: {$p['product_code']}).");
            $waLink = "https://wa.me/{$waNum}?text={$waMsg}";
            $actionBtns = '';
            if ($inStock) {
                if ($portalEnabled === '1') $actionBtns .= "<a href='{$siteUrl}/product.php?id={$p['id']}' class='btn btn-primary btn-sm'><i class='bi bi-bag-plus me-1'></i>Order</a>";
                if ($waEnabled === '1') $actionBtns .= "<a href='{$waLink}' target='_blank' class='btn btn-sm' style='background:#25d366;color:#fff'><i class='fab fa-whatsapp'></i></a>";
            } else {
                if ($waEnabled === '1') $actionBtns .= "<a href='{$waLink}' target='_blank' class='btn btn-outline-secondary btn-sm w-100'><i class='fab fa-whatsapp me-1'></i>Notify Me</a>";
            }
        ?>
        <div class="col-6 col-md-4 col-xl-3">
          <div class="product-card card">
            <a href="<?= $siteUrl ?>/product.php?id=<?= $p['id'] ?>">
              <div class="card-img-wrap">
                <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                <?= $badgeSale ?><?= $badgeNew ?><?= $oosOverlay ?>
              </div>
            </a>
            <div class="card-body">
              <a href="<?= $siteUrl ?>/product.php?id=<?= $p['id'] ?>" class="text-decoration-none">
                <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
              </a>
              <small class="text-muted"><?= htmlspecialchars($p['cat_name']) ?></small>
              <div class="color-dots mt-1"><?= $colorDotsHtml ?></div>
              <div class="mb-2"><?= $priceHtml ?></div>
              <div class="action-btns"><?= $actionBtns ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <nav class="mt-4">
        <ul class="pagination justify-content-center flex-wrap">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function applyFilter(key, value) {
    const params = new URLSearchParams(window.location.search);
    params.set(key, value);
    window.location.search = params.toString();
}
</script>
<?php renderFooter(); ?>
