<?php
require_once __DIR__ . '/includes/config.php';
start_secure_session('customer');
require_once __DIR__ . '/includes/theme.php';

$db = getDB();
$settings = getSettings();
$productId = intval($_GET['id'] ?? 0);

if (!$productId) { header('Location: /shop/shop.php'); exit; }

$stmt = $db->prepare("SELECT p.*, c.name as cat_name, c.slug as cat_slug FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=? AND p.status!='inactive'");
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product) { header('Location: /shop/shop.php'); exit; }

// Colors with images
$colors = $db->prepare("SELECT pc.*, GROUP_CONCAT(pi.image_path ORDER BY pi.sort_order SEPARATOR '|') as images FROM product_colors pc LEFT JOIN product_images pi ON pi.color_id=pc.id WHERE pc.product_id=? GROUP BY pc.id ORDER BY pc.sort_order");
$colors->execute([$productId]);
$colors = $colors->fetchAll();

// Sizes
$sizes = $db->prepare("SELECT * FROM product_sizes WHERE product_id=? ORDER BY sort_order");
$sizes->execute([$productId]);
$sizes = $sizes->fetchAll();

// Stock map: [color_id][size_id] = qty
$stockStmt = $db->prepare("SELECT color_id, size_id, quantity FROM product_stock WHERE product_id=?");
$stockStmt->execute([$productId]);
$stockMap = [];
foreach ($stockStmt->fetchAll() as $s) {
    $stockMap[$s['color_id']][$s['size_id']] = $s['quantity'];
}

// All images (no color)
$allImgs = $db->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY is_primary DESC, sort_order");
$allImgs->execute([$productId]);
$allImgs = $allImgs->fetchAll();

// Prepare stock JSON for JS
$stockJson = json_encode($stockMap);
$colorsJson = json_encode(array_map(fn($c) => ['id'=>$c['id'],'name'=>$c['color_name'],'hex'=>$c['color_hex'],'images'=>$c['images'] ? explode('|',$c['images']) : []], $colors));

$waNum = preg_replace('/[^0-9]/', '', $settings['contact_whatsapp'] ?? '');
$waEnabled = $settings['whatsapp_orders_enabled'] ?? '1';
$portalEnabled = $settings['portal_orders_enabled'] ?? '1';

$displayPrice = $product['is_on_sale'] && $product['sale_price'] ? $product['sale_price'] : $product['base_price'];
$currency = $settings['currency_symbol'] ?? 'Rs.';

renderHead(htmlspecialchars($product['name']));
renderNavbar();
?>

<div class="container my-4">
  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/shop/index.php">Home</a></li>
      <li class="breadcrumb-item"><a href="/shop/shop.php?cat=<?= urlencode($product['cat_slug']) ?>"><?= htmlspecialchars($product['cat_name']) ?></a></li>
      <li class="breadcrumb-item active"><?= htmlspecialchars($product['name']) ?></li>
    </ol>
  </nav>

  <div class="row g-4">
    <!-- Image Gallery -->
    <div class="col-lg-6">
      <div class="sticky-top" style="top:80px">
        <div class="border rounded-3 overflow-hidden mb-2 bg-light" style="height:420px">
          <img id="mainProductImg" src="<?= $allImgs ? UPLOAD_URL . 'products/' . $allImgs[0]['image_path'] : SITE_URL . '/assets/images/placeholder.png' ?>" class="w-100 h-100" style="object-fit:contain" alt="<?= htmlspecialchars($product['name']) ?>">
        </div>
        <div class="d-flex gap-2 flex-wrap" id="thumbGallery">
          <?php foreach ($allImgs as $img): ?>
          <img src="<?= UPLOAD_URL ?>products/<?= htmlspecialchars($img['image_path']) ?>" class="thumb-img rounded border" style="width:70px;height:70px;object-fit:cover;cursor:pointer;border-width:2px!important" onclick="setMainImg(this.src)" data-color="<?= $img['color_id'] ?>">
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Product Info -->
    <div class="col-lg-6">
      <span class="badge text-bg-secondary mb-2"><?= htmlspecialchars($product['cat_name']) ?></span>
      <h1 class="h2 fw-bold" style="font-family:'Playfair Display',serif"><?= htmlspecialchars($product['name']) ?></h1>
      <p class="text-muted small">Code: <strong><?= htmlspecialchars($product['product_code']) ?></strong></p>

      <!-- Price -->
      <div class="mb-3">
        <span class="fs-2 fw-bold" style="color:var(--primary)" id="displayPrice"><?= $currency ?> <?= number_format($displayPrice, 2) ?></span>
        <?php if ($product['is_on_sale'] && $product['sale_price']): ?>
        <span class="text-muted text-decoration-line-through ms-2 fs-5"><?= $currency ?> <?= number_format($product['base_price'], 2) ?></span>
        <span class="badge ms-1" style="background:var(--secondary)">SALE <?= $product['discount_percent'] ?>% OFF</span>
        <?php endif; ?>
      </div>

      <!-- Color Selection -->
      <?php if (!empty($colors)): ?>
      <div class="mb-3">
        <label class="fw-semibold mb-2">Color: <span id="selectedColorName" class="text-muted fw-normal">— choose one</span></label>
        <div class="d-flex gap-2 flex-wrap" id="colorSwatches">
          <?php foreach ($colors as $c): ?>
          <div class="color-swatch" style="background:<?= htmlspecialchars($c['color_hex']) ?>" title="<?= htmlspecialchars($c['color_name']) ?>"
               data-color-id="<?= $c['id'] ?>" data-color-name="<?= htmlspecialchars($c['color_name']) ?>"
               onclick="selectColor(this, <?= $c['id'] ?>, '<?= htmlspecialchars($c['color_name']) ?>')"></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Size Selection -->
      <?php if (!empty($sizes)): ?>
      <div class="mb-3">
        <label class="fw-semibold mb-2">Size: <span id="selectedSizeName" class="text-muted fw-normal">— choose one</span></label>
        <div class="d-flex gap-2 flex-wrap" id="sizeButtons">
          <?php foreach ($sizes as $sz): ?>
          <button class="size-btn" data-size-id="<?= $sz['id'] ?>" data-size-label="<?= htmlspecialchars($sz['size_label']) ?>"
                  onclick="selectSize(this, <?= $sz['id'] ?>, '<?= htmlspecialchars($sz['size_label']) ?>')">
            <?= htmlspecialchars($sz['size_label']) ?>
          </button>
          <?php endforeach; ?>
        </div>
        <div id="stockInfo" class="mt-2 small"></div>
      </div>
      <?php endif; ?>

      <!-- Quantity -->
      <div class="mb-3">
        <label class="fw-semibold mb-2">Quantity</label>
        <div class="input-group" style="max-width:140px">
          <button class="btn btn-outline-secondary" onclick="changeQty(-1)">-</button>
          <input type="number" id="qty" class="form-control text-center" value="1" min="1" max="99">
          <button class="btn btn-outline-secondary" onclick="changeQty(1)">+</button>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="d-grid gap-2" id="actionArea">
        <?php if ($portalEnabled === '1'): ?>
        <button class="btn btn-primary btn-lg fw-bold" onclick="addToCart()" id="btnAddCart">
          <i class="bi bi-cart-plus me-2"></i>Add to Cart & Order via Portal
        </button>
        <?php endif; ?>
        <?php if ($waEnabled === '1'): ?>
        <button class="btn btn-lg fw-bold" style="background:#25d366;color:#fff" onclick="orderViaWhatsApp()" id="btnWhatsApp">
          <i class="fab fa-whatsapp me-2"></i>Order via WhatsApp
        </button>
        <?php endif; ?>
      </div>

      <div id="selectionWarning" class="alert alert-warning mt-2 py-2 d-none"><i class="bi bi-exclamation-triangle me-2"></i>Please select color and size first.</div>

      <!-- Description -->
      <?php if ($product['description']): ?>
      <hr>
      <h5 class="fw-bold">Description</h5>
      <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
      <?php endif; ?>

      <!-- Share -->
      <hr>
      <div>
        <span class="fw-semibold me-2">Share:</span>
        <a href="https://wa.me/?text=<?= urlencode($product['name'] . ' ' . SITE_URL . '/product.php?id=' . $product['id']) ?>" target="_blank" class="btn btn-sm btn-outline-success me-1"><i class="fab fa-whatsapp"></i></a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(SITE_URL . '/product.php?id=' . $product['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary me-1"><i class="fab fa-facebook-f"></i></a>
      </div>
    </div>
  </div>
</div>

<script>
const stockMap = <?= $stockJson ?>;
const colorsData = <?= $colorsJson ?>;
const uploadUrl = '<?= UPLOAD_URL ?>products/';
const waNum = '<?= $waNum ?>';
const productName = '<?= addslashes($product['name']) ?>';
const productCode = '<?= addslashes($product['product_code']) ?>';

let selectedColorId = null;
let selectedSizeName = '';
let selectedSizeId = null;
let selectedColorName = '';

function setMainImg(src) {
    document.getElementById('mainProductImg').src = src;
}

function selectColor(el, colorId, colorName) {
    document.querySelectorAll('#colorSwatches .color-swatch').forEach(e => e.classList.remove('active'));
    el.classList.add('active');
    selectedColorId = colorId;
    selectedColorName = colorName;
    document.getElementById('selectedColorName').textContent = colorName;

    // Update images for this color
    const colorData = colorsData.find(c => c.id == colorId);
    if (colorData && colorData.images && colorData.images.length > 0) {
        setMainImg(uploadUrl + colorData.images[0]);
        // Highlight thumbs for this color
        document.querySelectorAll('#thumbGallery .thumb-img').forEach(img => {
            img.style.opacity = img.dataset.color == colorId ? '1' : '0.4';
            img.style.borderColor = img.dataset.color == colorId ? 'var(--primary)' : '#dee2e6';
        });
    }
    updateSizeAvailability();
}

function selectSize(el, sizeId, sizeLabel) {
    if (el.classList.contains('oos')) return;
    document.querySelectorAll('#sizeButtons .size-btn').forEach(e => e.classList.remove('active'));
    el.classList.add('active');
    selectedSizeId = sizeId;
    selectedSizeName = sizeLabel;
    document.getElementById('selectedSizeName').textContent = sizeLabel;
    updateStockInfo();
}

function updateSizeAvailability() {
    if (!selectedColorId) return;
    document.querySelectorAll('#sizeButtons .size-btn').forEach(btn => {
        const sizeId = btn.dataset.sizeId;
        const qty = (stockMap[selectedColorId] && stockMap[selectedColorId][sizeId]) ? stockMap[selectedColorId][sizeId] : 0;
        btn.classList.toggle('oos', qty <= 0);
        if (selectedSizeId == sizeId && qty <= 0) {
            selectedSizeId = null;
            selectedSizeName = '';
            btn.classList.remove('active');
            document.getElementById('selectedSizeName').textContent = '— choose one';
        }
    });
}

function updateStockInfo() {
    const info = document.getElementById('stockInfo');
    if (selectedColorId && selectedSizeId) {
        const qty = (stockMap[selectedColorId] && stockMap[selectedColorId][selectedSizeId]) ? stockMap[selectedColorId][selectedSizeId] : 0;
        if (qty <= 0) info.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Out of Stock</span>';
        else if (qty <= 5) info.innerHTML = `<span class="text-warning"><i class="bi bi-exclamation-circle"></i> Only ${qty} left!</span>`;
        else info.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> In Stock</span>`;
    }
}

function changeQty(delta) {
    const input = document.getElementById('qty');
    const v = parseInt(input.value) + delta;
    input.value = Math.max(1, Math.min(99, v));
}

function validate() {
    const warn = document.getElementById('selectionWarning');
    const hasColor = document.querySelectorAll('#colorSwatches .color-swatch').length === 0 || selectedColorId;
    const hasSize = document.querySelectorAll('#sizeButtons .size-btn').length === 0 || selectedSizeId;
    if (!hasColor || !hasSize) {
        warn.classList.remove('d-none');
        setTimeout(() => warn.classList.add('d-none'), 3000);
        return false;
    }
    return true;
}

function orderViaWhatsApp() {
    if (!validate()) return;
    const qty = document.getElementById('qty').value;
    const msg = encodeURIComponent(`Hello! I want to order:\n\n🛍 *Item:* ${productName}\n📦 *Code:* ${productCode}\n🎨 *Color:* ${selectedColorName || 'N/A'}\n📏 *Size:* ${selectedSizeName || 'N/A'}\n🔢 *Quantity:* ${qty}\n\nPlease confirm availability and payment details.`);
    window.open(`https://wa.me/${waNum}?text=${msg}`, '_blank');
}

function addToCart() {
    if (!validate()) return;
    const qty = document.getElementById('qty').value;
    fetch('/shop/api/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action:'add', product_id: <?= $product['id'] ?>, color_id: selectedColorId, size_id: selectedSizeId, quantity: qty})
    }).then(r => r.json()).then(d => {
        if (d.success) {
            toastr.success('Added to cart!');
            updateCartCount();
        } else {
            toastr.error(d.error || 'Could not add to cart');
        }
    });
}
</script>

<?php renderFooter(); ?>
