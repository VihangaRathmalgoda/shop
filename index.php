<?php
require_once __DIR__ . '/includes/config.php';
start_secure_session('customer');
require_once __DIR__ . '/includes/theme.php';

$db = getDB();
$settings = getSettings();

// Banners
$banners = $db->query("SELECT * FROM banners WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll();

// Featured products
$featured = $db->query("SELECT p.*,c.name as cat_name,
  (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) as primary_image,
  (SELECT GROUP_CONCAT(color_hex ORDER BY sort_order SEPARATOR '|') FROM product_colors WHERE product_id=p.id) as color_hexes,
  (SELECT GROUP_CONCAT(color_name ORDER BY sort_order SEPARATOR '|') FROM product_colors WHERE product_id=p.id) as color_names,
  (SELECT SUM(quantity) FROM product_stock WHERE product_id=p.id) as total_stock
  FROM products p LEFT JOIN categories c ON p.category_id=c.id
  WHERE p.is_featured=1 AND p.status='active' ORDER BY p.created_at DESC LIMIT 8")->fetchAll();

// New arrivals
$newArrivals = $db->query("SELECT p.*,c.name as cat_name,
  (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) as primary_image,
  (SELECT GROUP_CONCAT(color_hex ORDER BY sort_order SEPARATOR '|') FROM product_colors WHERE product_id=p.id) as color_hexes,
  (SELECT SUM(quantity) FROM product_stock WHERE product_id=p.id) as total_stock
  FROM products p LEFT JOIN categories c ON p.category_id=c.id
  WHERE p.is_new_arrival=1 AND p.status='active' ORDER BY p.created_at DESC LIMIT 8")->fetchAll();

// Categories
$categories = $db->query("SELECT * FROM categories WHERE is_active=1 AND parent_id IS NULL ORDER BY sort_order LIMIT 8")->fetchAll();

// Active offers
$offers = $db->query("SELECT * FROM offers WHERE is_active=1 AND start_date<=CURDATE() AND end_date>=CURDATE() LIMIT 4")->fetchAll();

renderHead('Welcome - Fresh Fashion', '');
renderNavbar();
?>

<!-- Hero Carousel -->
<?php if (!empty($banners)): ?>
<div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
  <div class="carousel-indicators">
    <?php foreach ($banners as $i => $b): ?>
    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>" <?= $i===0?'class="active"':'' ?>></button>
    <?php endforeach; ?>
  </div>
  <div class="carousel-inner">
    <?php foreach ($banners as $i => $b): ?>
    <div class="carousel-item <?= $i===0?'active':'' ?>">
      <img src="<?= UPLOAD_URL ?>banners/<?= htmlspecialchars($b['image_path']) ?>" class="d-block w-100" alt="<?= htmlspecialchars($b['title']) ?>">
      <?php if ($b['title'] || $b['subtitle']): ?>
      <div class="carousel-caption d-none d-md-block">
        <?php if ($b['title']): ?><h2 class="fw-bold"><?= htmlspecialchars($b['title']) ?></h2><?php endif; ?>
        <?php if ($b['subtitle']): ?><p><?= htmlspecialchars($b['subtitle']) ?></p><?php endif; ?>
        <?php if ($b['button_text'] && $b['button_link']): ?>
        <a href="<?= htmlspecialchars($b['button_link']) ?>" class="btn btn-primary btn-lg"><?= htmlspecialchars($b['button_text']) ?></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
  <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
</div>
<?php else: ?>
<div class="py-5 text-center" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color:#fff; min-height:320px; display:flex; align-items:center; justify-content:center; flex-direction:column;">
  <h1 class="fw-bold display-4" style="font-family:'Playfair Display',serif"><?= htmlspecialchars($settings['site_name'] ?? 'Fashion Store') ?></h1>
  <p class="lead mt-2"><?= htmlspecialchars($settings['site_tagline'] ?? 'Style for Everyone') ?></p>
  <a href="/shop/shop.php" class="btn btn-light btn-lg mt-3 fw-bold">Shop Now</a>
</div>
<?php endif; ?>

<!-- Active Offers Strip -->
<?php if (!empty($offers)): ?>
<div style="background: var(--accent); color:#fff;" class="py-2 text-center">
  <div class="container">
    <?php foreach ($offers as $o): ?>
    <span class="me-4 fw-semibold"><i class="bi bi-tag-fill me-1"></i><?= htmlspecialchars($o['offer_name']) ?>: <?= $o['discount_type']==='percent' ? $o['discount_value'].'% OFF' : 'Rs. '.$o['discount_value'].' OFF' ?> &mdash; Code: <kbd><?= htmlspecialchars($o['offer_code']) ?></kbd></span>
    <?php endforeach; ?>
    <a href="/shop/offers.php" class="btn btn-sm btn-dark ms-2">View All</a>
  </div>
</div>
<?php endif; ?>

<div class="container my-5">

  <!-- Categories -->
  <?php if (!empty($categories)): ?>
  <h2 class="section-title">Shop by Category</h2>
  <div class="row g-3 mb-5">
    <?php foreach ($categories as $cat): ?>
    <div class="col-6 col-md-3 col-lg-3">
      <a href="/shop/shop.php?cat=<?= urlencode($cat['slug']) ?>" class="text-decoration-none">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:12px; transition:transform .2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
          <?php if ($cat['image']): ?>
          <img src="<?= UPLOAD_URL ?>products/<?= htmlspecialchars($cat['image']) ?>" class="rounded-circle mx-auto mb-2" width="70" height="70" style="object-fit:cover;">
          <?php else: ?>
          <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:70px;height:70px;background:var(--bg);border:2px solid var(--primary)">
            <i class="bi bi-bag fs-2" style="color:var(--primary)"></i>
          </div>
          <?php endif; ?>
          <h6 class="fw-bold" style="color:var(--primary)"><?= htmlspecialchars($cat['name']) ?></h6>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Featured Products -->
  <?php if (!empty($featured)): ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="section-title mb-0">Featured Products</h2>
    <a href="/shop/shop.php?filter=featured" class="btn btn-outline-primary btn-sm">View All</a>
  </div>
  <div class="row g-3 mb-5">
    <?php foreach ($featured as $p): renderProductCard($p, $settings, $db); endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- New Arrivals -->
  <?php if (!empty($newArrivals)): ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="section-title mb-0">New Arrivals</h2>
    <a href="/shop/shop.php?filter=new" class="btn btn-outline-primary btn-sm">View All</a>
  </div>
  <div class="row g-3">
    <?php foreach ($newArrivals as $p): renderProductCard($p, $settings, $db); endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Why choose us -->
  <div class="row g-3 mt-5 text-center">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 h-100">
        <i class="bi bi-truck fs-2 mb-2" style="color:var(--primary)"></i>
        <h6 class="fw-bold">Island-Wide Delivery</h6>
        <small class="text-muted">We deliver across Sri Lanka</small>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 h-100">
        <i class="bi bi-shield-check fs-2 mb-2" style="color:var(--primary)"></i>
        <h6 class="fw-bold">Genuine Products</h6>
        <small class="text-muted">100% authentic clothing</small>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 h-100">
        <i class="fab fa-whatsapp fs-2 mb-2" style="color:#25d366"></i>
        <h6 class="fw-bold">WhatsApp Orders</h6>
        <small class="text-muted">Easy order via WhatsApp</small>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 h-100">
        <i class="bi bi-arrow-counterclockwise fs-2 mb-2" style="color:var(--primary)"></i>
        <h6 class="fw-bold">Easy Returns</h6>
        <small class="text-muted">Hassle-free return policy</small>
      </div>
    </div>
  </div>

</div>

<?php
renderFooter();

// ---- Product Card Renderer ----
function renderProductCard($p, $settings, $db) {
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
        foreach ($hexes as $i => $hex) {
            $colors[] = ['hex' => $hex, 'name' => $names[$i] ?? $hex];
        }
    }

    $badgeSale = $p['is_on_sale'] ? '<span class="badge-sale">SALE</span>' : '';
    $badgeNew  = $p['is_new_arrival'] ? '<span class="badge-new">NEW</span>' : '';
    $oosOverlay = !$inStock ? '<div class="badge-oos">OUT OF STOCK</div>' : '';

    $priceHtml = $salePrice
        ? "<span class='price'>{$currency} {$salePrice}</span> <span class='price-original'>{$currency} {$price}</span>"
        : "<span class='price'>{$currency} {$price}</span>";

    $colorDotsHtml = '';
    foreach ($colors as $c) {
        $colorDotsHtml .= "<span class='color-dot' style='background:{$c['hex']}' title='{$c['name']}'></span>";
    }

    $waMsg = urlencode("Hi! I'm interested in: {$p['name']} (Code: {$p['product_code']}). Please share details.");
    $waLink = "https://wa.me/{$waNum}?text={$waMsg}";

    $actionBtns = '';
    if ($inStock) {
        if ($portalEnabled === '1') {
            $actionBtns .= "<a href='{$siteUrl}/product.php?id={$p['id']}' class='btn btn-primary btn-sm'><i class='bi bi-bag-plus me-1'></i>Order</a>";
        }
        if ($waEnabled === '1') {
            $actionBtns .= "<a href='{$waLink}' target='_blank' class='btn btn-sm' style='background:#25d366;color:#fff'><i class='fab fa-whatsapp'></i></a>";
        }
    } else {
        if ($waEnabled === '1') {
            $actionBtns .= "<a href='{$waLink}' target='_blank' class='btn btn-outline-secondary btn-sm w-100'><i class='fab fa-whatsapp me-1'></i>Notify Me</a>";
        }
    }

    echo <<<HTML
<div class="col-6 col-md-4 col-lg-3">
  <div class="product-card card">
    <a href="{$siteUrl}/product.php?id={$p['id']}">
      <div class="card-img-wrap">
        <img src="{$img}" alt="{$p['name']}" loading="lazy">
        {$badgeSale}{$badgeNew}{$oosOverlay}
      </div>
    </a>
    <div class="card-body">
      <a href="{$siteUrl}/product.php?id={$p['id']}" class="text-decoration-none">
        <div class="product-name">{$p['name']}</div>
      </a>
      <small class="text-muted">{$p['cat_name']}</small>
      <div class="color-dots mt-1">{$colorDotsHtml}</div>
      <div class="mb-2">{$priceHtml}</div>
      <div class="action-btns">{$actionBtns}</div>
    </div>
  </div>
</div>
HTML;
}
?>
