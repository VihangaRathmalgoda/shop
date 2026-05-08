<?php
require_once __DIR__ . '/includes/config.php';
start_secure_session('customer');
require_once __DIR__ . '/includes/theme.php';

$db       = getDB();
$settings = getSettings();
$customerId = $_SESSION['customer_id'] ?? null;
$sessionId  = session_id();

// Load cart items
function loadCart($db, $customerId, $sessionId) {
    if ($customerId) {
        $stmt = $db->prepare("SELECT c.id as cart_id, c.quantity, p.id as product_id, p.name, p.base_price, p.sale_price, p.is_on_sale, p.product_code,
            pc.id as color_id, pc.color_name, pc.color_hex, ps.id as size_id, ps.size_label,
            (SELECT image_path FROM product_images WHERE product_id=c.product_id AND color_id=c.color_id LIMIT 1) as img,
            (SELECT quantity FROM product_stock WHERE product_id=c.product_id AND color_id=c.color_id AND size_id=c.size_id) as stock_qty
            FROM cart c JOIN products p ON c.product_id=p.id LEFT JOIN product_colors pc ON c.color_id=pc.id LEFT JOIN product_sizes ps ON c.size_id=ps.id
            WHERE c.customer_id=?");
        $stmt->execute([$customerId]);
    } else {
        $stmt = $db->prepare("SELECT c.id as cart_id, c.quantity, p.id as product_id, p.name, p.base_price, p.sale_price, p.is_on_sale, p.product_code,
            pc.id as color_id, pc.color_name, pc.color_hex, ps.id as size_id, ps.size_label,
            (SELECT image_path FROM product_images WHERE product_id=c.product_id AND color_id=c.color_id LIMIT 1) as img,
            (SELECT quantity FROM product_stock WHERE product_id=c.product_id AND color_id=c.color_id AND size_id=c.size_id) as stock_qty
            FROM cart c JOIN products p ON c.product_id=p.id LEFT JOIN product_colors pc ON c.color_id=pc.id LEFT JOIN product_sizes ps ON c.size_id=ps.id
            WHERE c.session_id=?");
        $stmt->execute([$sessionId]);
    }
    return $stmt->fetchAll();
}

// Handle checkout POST
$checkoutError = '';
$orderPlaced   = false;
$newOrderNumber = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    csrf_check();
    $cartItems = loadCart($db, $customerId, $sessionId);
    if (!empty($cartItems)) {
        $custName    = trim($_POST['customer_name'] ?? '');
        $custEmail   = trim($_POST['customer_email'] ?? '');
        $custPhone   = trim($_POST['customer_phone'] ?? '');
        $custWa      = trim($_POST['customer_whatsapp'] ?? $custPhone);
        $address     = trim($_POST['delivery_address'] ?? '');
        $city        = trim($_POST['delivery_city'] ?? '');
        $postal      = trim($_POST['delivery_postal'] ?? '');
        $payMethod   = $_POST['payment_method'] ?? 'cod';
        $offerCode   = strtoupper(trim($_POST['offer_code'] ?? ''));
        $notes       = trim($_POST['notes'] ?? '');

        $allowedMethods = ['cod','bank_transfer','payhere','koko'];
        if (!in_array($payMethod, $allowedMethods, true)) $payMethod = 'cod';

        if (!$custName || !$custPhone || !$address) {
            $checkoutError = 'Please fill all required fields.';
        } else {
            // Calculate totals
            $subtotal = 0;
            foreach ($cartItems as $item) {
                $price = ($item['is_on_sale'] && $item['sale_price']) ? $item['sale_price'] : $item['base_price'];
                $subtotal += $price * $item['quantity'];
            }

            $shippingFee      = floatval($settings['shipping_fee'] ?? 350);
            $freeShippingAbove = floatval($settings['free_shipping_above'] ?? 5000);
            if ($subtotal >= $freeShippingAbove) $shippingFee = 0;

            // Resolve offer (don't increment usage yet — that happens inside the transaction)
            $discountAmount = 0;
            $offerId        = null;
            $offer          = null;
            if ($offerCode) {
                $offerStmt = $db->prepare("SELECT * FROM offers WHERE offer_code=? AND is_active=1
                    AND (start_date IS NULL OR start_date<=CURDATE())
                    AND (end_date IS NULL OR end_date>=CURDATE())");
                $offerStmt->execute([$offerCode]);
                $offer = $offerStmt->fetch();
                if ($offer && $subtotal >= $offer['min_order_amount']) {
                    if ($offer['discount_type'] === 'percent') {
                        $discountAmount = $subtotal * ($offer['discount_value'] / 100);
                        if ($offer['max_discount_amount']) $discountAmount = min($discountAmount, $offer['max_discount_amount']);
                    } else {
                        $discountAmount = min($offer['discount_value'], $subtotal);
                    }
                    $offerId = $offer['id'];
                } else {
                    $checkoutError = 'Invalid or expired promo code.';
                    $offer = null;
                }
            }

            if (!$checkoutError) {
                $total       = $subtotal - $discountAmount + $shippingFee;
                $orderNumber = generateOrderNumber();

                try {
                    $db->beginTransaction();

                    $db->prepare("INSERT INTO orders (order_number,customer_id,customer_name,customer_email,customer_phone,customer_whatsapp,delivery_address,delivery_city,delivery_postal,subtotal,discount_amount,shipping_fee,total_amount,offer_id,offer_code,payment_method,order_status,order_source,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending','portal',?)")
                       ->execute([$orderNumber,$customerId,$custName,$custEmail,$custPhone,$custWa,$address,$city,$postal,$subtotal,$discountAmount,$shippingFee,$total,$offerId,$offerCode ?: null,$payMethod,$notes]);
                    $orderId = $db->lastInsertId();

                    $stockUpd = $db->prepare("UPDATE product_stock SET quantity=quantity-? WHERE product_id=? AND color_id=? AND size_id=? AND quantity>=?");
                    $itemIns  = $db->prepare("INSERT INTO order_items (order_id,product_id,product_code,product_name,color_name,size_label,quantity,unit_price,total_price) VALUES (?,?,?,?,?,?,?,?,?)");

                    foreach ($cartItems as $item) {
                        $price = ($item['is_on_sale'] && $item['sale_price']) ? $item['sale_price'] : $item['base_price'];
                        $itemIns->execute([$orderId,$item['product_id'],$item['product_code'],$item['name'],$item['color_name'],$item['size_label'],$item['quantity'],$price,$price*$item['quantity']]);

                        // Atomic stock decrement: row only updates when current stock can cover the order.
                        $stockUpd->execute([$item['quantity'],$item['product_id'],$item['color_id'],$item['size_id'],$item['quantity']]);
                        if ($stockUpd->rowCount() !== 1) {
                            throw new RuntimeException("Sorry — '" . $item['name'] . "' just went out of stock for the selected size/colour. Please review your cart and try again.");
                        }
                    }

                    if ($offer) {
                        $db->prepare("UPDATE offers SET used_count=used_count+1 WHERE id=?")->execute([$offer['id']]);
                    }

                    if ($customerId) {
                        $db->prepare("DELETE FROM cart WHERE customer_id=?")->execute([$customerId]);
                    } else {
                        $db->prepare("DELETE FROM cart WHERE session_id=?")->execute([$sessionId]);
                    }

                    $db->commit();

                    $orderPlaced    = true;
                    $newOrderNumber = $orderNumber;
                    $newOrderId     = $orderId;
                } catch (Throwable $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    error_log('[checkout] ' . $e->getMessage());
                    $checkoutError = ($e instanceof RuntimeException) ? $e->getMessage() : 'Could not place your order. Please try again.';
                }
            }
        }
    }
}

$cartItems = loadCart($db, $customerId, $sessionId);
$subtotal  = 0;
foreach ($cartItems as &$item) {
    $item['unit_price'] = ($item['is_on_sale'] && $item['sale_price']) ? $item['sale_price'] : $item['base_price'];
    $item['line_total'] = $item['unit_price'] * $item['quantity'];
    $item['img_url']    = $item['img'] ? UPLOAD_URL . 'products/' . $item['img'] : SITE_URL . '/assets/images/placeholder.png';
    $subtotal += $item['line_total'];
}
$shippingFee = floatval($settings['shipping_fee'] ?? 350);
if ($subtotal >= floatval($settings['free_shipping_above'] ?? 5000)) $shippingFee = 0;

$payhereEnabled = $settings['payhere_enabled'] ?? '0';
$kokoEnabled    = $settings['koko_enabled'] ?? '0';
$codEnabled     = $settings['cod_enabled'] ?? '1';

// Customer info for prefill
$customer = null;
if ($customerId) { $c = $db->prepare("SELECT * FROM customers WHERE id=?"); $c->execute([$customerId]); $customer = $c->fetch(); }

renderHead('Cart & Checkout');
renderNavbar();
?>

<div class="container my-4">
<?php if ($orderPlaced): ?>
<!-- ORDER SUCCESS -->
<div class="row justify-content-center">
  <div class="col-md-7 text-center py-5">
    <div class="mb-4" style="font-size:5rem">🎉</div>
    <h2 class="fw-bold" style="color:var(--primary)">Order Placed!</h2>
    <p class="lead text-muted">Thank you for your order.</p>
    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius:12px">
      <p class="mb-1 text-muted">Order Number</p>
      <h4 class="fw-bold" style="color:var(--primary)"><?= htmlspecialchars($newOrderNumber) ?></h4>
      <p class="text-muted small mt-2">We'll process your order and contact you on WhatsApp. You can track your order status in your account.</p>
    </div>
    <div class="d-flex gap-3 justify-content-center flex-wrap">
      <?php if ($customerId): ?>
      <a href="/shop/customer/account.php?tab=orders" class="btn btn-primary fw-bold">View My Orders</a>
      <?php endif; ?>
      <a href="/shop/index.php" class="btn btn-outline-secondary">Continue Shopping</a>
    </div>
  </div>
</div>

<?php elseif (empty($cartItems)): ?>
<!-- EMPTY CART -->
<div class="text-center py-5">
  <div style="font-size:5rem">🛒</div>
  <h3 class="fw-bold mt-3">Your cart is empty</h3>
  <p class="text-muted">Add some items to get started!</p>
  <a href="/shop/shop.php" class="btn btn-primary btn-lg mt-2">Browse Products</a>
</div>

<?php else: ?>
<!-- CART + CHECKOUT -->
<h2 class="fw-bold mb-4">Cart & Checkout</h2>
<?php if ($checkoutError): ?><div class="alert alert-danger"><?= htmlspecialchars($checkoutError) ?></div><?php endif; ?>

<div class="row g-4">
  <!-- Cart Items -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm mb-3" style="border-radius:12px">
      <div class="card-header bg-white fw-bold">Cart Items (<?= count($cartItems) ?>)</div>
      <div class="card-body p-0" id="cartItemsContainer">
        <?php foreach ($cartItems as $item): ?>
        <div class="d-flex align-items-center gap-3 p-3 border-bottom cart-item" id="cart-<?= $item['cart_id'] ?>">
          <a href="/shop/product.php?id=<?= $item['product_id'] ?>"><img src="<?= $item['img_url'] ?>" style="width:64px;height:64px;object-fit:cover;border-radius:8px"></a>
          <div class="flex-grow-1">
            <a href="/shop/product.php?id=<?= $item['product_id'] ?>" class="fw-semibold text-dark d-block"><?= htmlspecialchars($item['name']) ?></a>
            <small class="text-muted"><?= htmlspecialchars($item['color_name']) ?> / <?= htmlspecialchars($item['size_label']) ?></small>
            <div class="fw-bold" style="color:var(--primary)">Rs. <?= number_format($item['unit_price'], 2) ?></div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:100px">
              <button class="btn btn-outline-secondary" onclick="updateCartQty(<?= $item['cart_id'] ?>, <?= $item['quantity'] - 1 ?>)">-</button>
              <input type="number" class="form-control text-center" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock_qty'] ?>" onchange="updateCartQty(<?= $item['cart_id'] ?>, this.value)">
              <button class="btn btn-outline-secondary" onclick="updateCartQty(<?= $item['cart_id'] ?>, <?= $item['quantity'] + 1 ?>)">+</button>
            </div>
            <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(<?= $item['cart_id'] ?>)"><i class="bi bi-trash"></i></button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Promo Code -->
    <div class="card border-0 shadow-sm mb-3" style="border-radius:12px">
      <div class="card-body">
        <div class="input-group">
          <input type="text" id="promoInput" class="form-control" placeholder="Promo code (e.g. SALE20)" style="text-transform:uppercase">
          <button class="btn btn-outline-primary" onclick="applyPromo()">Apply</button>
        </div>
        <div id="promoMsg" class="mt-2 small"></div>
      </div>
    </div>
  </div>

  <!-- Checkout Form + Order Summary -->
  <div class="col-lg-5">
    <!-- Order Summary -->
    <div class="card border-0 shadow-sm mb-3" style="border-radius:12px">
      <div class="card-header bg-white fw-bold">Order Summary</div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span id="summSubtotal">Rs. <?= number_format($subtotal, 2) ?></span></div>
        <div class="d-flex justify-content-between mb-2 text-success d-none" id="discountRow"><span>Discount</span><span id="summDiscount">-Rs. 0</span></div>
        <div class="d-flex justify-content-between mb-2"><span>Shipping</span><span><?= $shippingFee > 0 ? 'Rs. '.number_format($shippingFee,2) : '<span class="text-success">FREE</span>' ?></span></div>
        <hr>
        <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span id="summTotal" style="color:var(--primary)">Rs. <?= number_format($subtotal + $shippingFee, 2) ?></span></div>
        <?php if ($shippingFee == 0): ?><small class="text-success"><i class="bi bi-truck me-1"></i>Free shipping applied!</small><?php endif; ?>
        <?php if ($subtotal < floatval($settings['free_shipping_above'] ?? 5000)): ?>
        <small class="text-muted d-block mt-1"><i class="bi bi-info-circle me-1"></i>Add Rs. <?= number_format(floatval($settings['free_shipping_above'] ?? 5000) - $subtotal, 2) ?> more for free shipping!</small>
        <?php endif; ?>
      </div>
    </div>

    <!-- Checkout Form -->
    <form method="POST" id="checkoutForm">
      <?= csrf_field() ?>
      <input type="hidden" name="checkout" value="1">
      <input type="hidden" name="offer_code" id="hiddenOfferCode" value="">
      <div class="card border-0 shadow-sm mb-3" style="border-radius:12px">
        <div class="card-header bg-white fw-bold">Delivery Details</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Full Name *</label>
              <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($customer ? $customer['first_name'].' '.$customer['last_name'] : '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone *</label>
              <input type="tel" name="customer_phone" class="form-control" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" required placeholder="+94771234567">
            </div>
            <div class="col-md-6">
              <label class="form-label">WhatsApp</label>
              <input type="tel" name="customer_whatsapp" class="form-control" value="<?= htmlspecialchars($customer['whatsapp'] ?? $customer['phone'] ?? '') ?>" placeholder="+94771234567">
            </div>
            <div class="col-12">
              <label class="form-label">Email</label>
              <input type="email" name="customer_email" class="form-control" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Delivery Address *</label>
              <textarea name="delivery_address" class="form-control" rows="2" required><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
            </div>
            <div class="col-md-7">
              <label class="form-label">City</label>
              <input type="text" name="delivery_city" class="form-control" value="<?= htmlspecialchars($customer['city'] ?? '') ?>">
            </div>
            <div class="col-md-5">
              <label class="form-label">Postal Code</label>
              <input type="text" name="delivery_postal" class="form-control" value="<?= htmlspecialchars($customer['postal_code'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Order Notes</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Special instructions..."></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment Method -->
      <div class="card border-0 shadow-sm mb-3" style="border-radius:12px">
        <div class="card-header bg-white fw-bold">Payment Method</div>
        <div class="card-body">
          <?php if ($codEnabled === '1'): ?>
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="payment_method" id="pmCOD" value="cod" checked>
            <label class="form-check-label fw-semibold" for="pmCOD"><i class="bi bi-cash-coin me-2 text-success"></i>Cash on Delivery</label>
            <p class="text-muted small mb-0 ms-4">Pay when you receive your order</p>
          </div>
          <?php endif; ?>
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="payment_method" id="pmBank" value="bank_transfer">
            <label class="form-check-label fw-semibold" for="pmBank"><i class="bi bi-bank me-2 text-primary"></i>Bank Transfer</label>
            <p class="text-muted small mb-0 ms-4">Transfer &amp; upload slip after ordering</p>
          </div>
          <?php if ($payhereEnabled === '1'): ?>
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="payment_method" id="pmPayhere" value="payhere">
            <label class="form-check-label fw-semibold" for="pmPayhere"><span class="badge" style="background:var(--primary)">PayHere</span> Online Payment</label>
            <p class="text-muted small mb-0 ms-4">Visa, Master, AMEX, Frimi</p>
          </div>
          <?php endif; ?>
          <?php if ($kokoEnabled === '1'): ?>
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="payment_method" id="pmKoko" value="koko">
            <label class="form-check-label fw-semibold" for="pmKoko">🦁 <span class="badge bg-dark">Koko</span> Buy Now, Pay Later</label>
            <p class="text-muted small mb-0 ms-4">Split into 3 easy payments</p>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$customerId): ?>
      <div class="alert alert-info py-2 small"><i class="bi bi-person-circle me-2"></i><a href="/shop/customer/login.php?redirect=/shop/cart.php">Sign in</a> to track your orders easily.</div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
        <i class="bi bi-bag-check me-2"></i>Place Order — Rs. <?= number_format($subtotal + $shippingFee, 2) ?>
      </button>
    </form>
  </div>
</div>
<?php endif; ?>
</div>

<script>
function updateCartQty(cartId, qty) {
    qty = Math.max(0, parseInt(qty));
    fetch('/shop/api/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action:'update', cart_id:cartId, quantity:qty})
    }).then(() => location.reload());
}
function removeFromCart(cartId) {
    fetch('/shop/api/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action:'remove', cart_id:cartId})
    }).then(() => location.reload());
}
function applyPromo() {
    const code = document.getElementById('promoInput').value.trim().toUpperCase();
    if (!code) return;
    fetch(`/shop/api/apply_promo.php?code=${code}&subtotal=<?= $subtotal ?>`)
      .then(r => r.json())
      .then(d => {
        const msg = document.getElementById('promoMsg');
        if (d.success) {
            msg.innerHTML = `<span class="text-success"><i class="bi bi-check-circle me-1"></i>${d.message}</span>`;
            document.getElementById('hiddenOfferCode').value = code;
            document.getElementById('discountRow').classList.remove('d-none');
            document.getElementById('summDiscount').textContent = '-Rs. ' + d.discount.toFixed(2);
            const newTotal = <?= $subtotal + $shippingFee ?> - d.discount;
            document.getElementById('summTotal').textContent = 'Rs. ' + newTotal.toFixed(2);
        } else {
            msg.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>${d.error}</span>`;
            document.getElementById('hiddenOfferCode').value = '';
        }
    });
}
</script>
<?php renderFooter(); ?>
