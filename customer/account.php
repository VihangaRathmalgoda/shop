<?php
require_once __DIR__ . '/../includes/config.php';
start_secure_session('customer');
require_once __DIR__ . '/../includes/theme.php';
requireCustomer();

$db = getDB();
$customerId = $_SESSION['customer_id'];
$tab = $_GET['tab'] ?? 'orders';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $address   = trim($_POST['address'] ?? '');
        $city      = trim($_POST['city'] ?? '');
        $postal    = trim($_POST['postal_code'] ?? '');
        $db->prepare("UPDATE customers SET first_name=?,last_name=?,phone=?,whatsapp=?,address=?,city=?,postal_code=? WHERE id=?")
           ->execute([$firstName,$lastName,$phone,$phone,$address,$city,$postal,$customerId]);
        $_SESSION['customer_name'] = "$firstName $lastName";
        $success = 'Profile updated!';
    }
    if ($act === 'upload_slip') {
        $orderId = intval($_POST['order_id'] ?? 0);
        if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
            // Ensure slips directory
            $slipDir = UPLOAD_PATH . 'slips/';
            if (!is_dir($slipDir)) mkdir($slipDir, 0755, true);
            $res = uploadImage($_FILES['payment_slip'], $slipDir, 'slip');
            if (isset($res['filename'])) {
                $db->prepare("UPDATE orders SET payment_slip=?, payment_status='slip_uploaded', updated_at=NOW() WHERE id=? AND customer_id=?")
                   ->execute([$res['filename'], $orderId, $customerId]);
                $success = 'Payment slip uploaded! We will verify and process your order soon.';
            }
        }
    }
}

$customer = $db->prepare("SELECT * FROM customers WHERE id=?"); $customer->execute([$customerId]); $customer = $customer->fetch();
$orders   = $db->prepare("SELECT o.*, COUNT(oi.id) as item_count FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id WHERE o.customer_id=? GROUP BY o.id ORDER BY o.created_at DESC");
$orders->execute([$customerId]);
$orders = $orders->fetchAll();

$statusColors = ['pending'=>'warning','confirmed'=>'info','processing'=>'primary','shipped'=>'secondary','delivered'=>'success','cancelled'=>'danger','returned'=>'dark'];

renderHead('My Account');
renderNavbar();
?>

<div class="container my-4">
  <div class="row g-4">
    <!-- Sidebar -->
    <div class="col-lg-3">
      <div class="card border-0 shadow-sm" style="border-radius:12px">
        <div class="card-body text-center p-4">
          <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:70px;height:70px;background:var(--primary);color:#fff;font-size:1.8rem;font-weight:700">
            <?= strtoupper(substr($customer['first_name'],0,1)) ?>
          </div>
          <h6 class="fw-bold mb-0"><?= htmlspecialchars($customer['first_name'].' '.$customer['last_name']) ?></h6>
          <small class="text-muted"><?= htmlspecialchars($customer['email']) ?></small>
        </div>
        <div class="list-group list-group-flush" style="border-radius:0 0 12px 12px">
          <a href="?tab=orders" class="list-group-item list-group-item-action <?= $tab==='orders'?'active':'' ?>"><i class="bi bi-bag-check me-2"></i>My Orders</a>
          <a href="?tab=profile" class="list-group-item list-group-item-action <?= $tab==='profile'?'active':'' ?>"><i class="bi bi-person me-2"></i>Profile</a>
          <a href="/shop/customer/logout.php" class="list-group-item list-group-item-action text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="col-lg-9">
      <?php if (isset($success)): ?>
      <div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if ($tab === 'orders'): ?>
      <h4 class="fw-bold mb-3">My Orders</h4>
      <?php if (empty($orders)): ?>
      <div class="card border-0 shadow-sm text-center p-5" style="border-radius:12px">
        <i class="bi bi-bag-x fs-1 text-muted mb-3"></i>
        <h5>No orders yet</h5>
        <p class="text-muted">Start shopping and your orders will appear here.</p>
        <a href="/shop/shop.php" class="btn btn-primary">Shop Now</a>
      </div>
      <?php else: ?>
      <?php foreach ($orders as $o): ?>
      <div class="card border-0 shadow-sm mb-3" style="border-radius:12px">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3" style="border-radius:12px 12px 0 0">
          <div>
            <span class="fw-bold">#<?= htmlspecialchars($o['order_number']) ?></span>
            <span class="text-muted ms-2 small"><?= date('d M Y', strtotime($o['created_at'])) ?></span>
          </div>
          <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-<?= $statusColors[$o['order_status']] ?? 'secondary' ?>"><?= ucfirst($o['order_status']) ?></span>
            <a href="?tab=order_detail&id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary py-0">View</a>
          </div>
        </div>
        <div class="card-body py-3">
          <div class="row text-center text-md-start g-2">
            <div class="col-6 col-md-3"><small class="text-muted d-block">Items</small><strong><?= $o['item_count'] ?></strong></div>
            <div class="col-6 col-md-3"><small class="text-muted d-block">Total</small><strong>Rs. <?= number_format($o['total_amount']) ?></strong></div>
            <div class="col-6 col-md-3"><small class="text-muted d-block">Payment</small>
              <span class="badge bg-<?= ['pending'=>'warning','slip_uploaded'=>'info','verified'=>'success','failed'=>'danger'][$o['payment_status']]??'secondary' ?> <?= $o['payment_status']==='pending'?'text-dark':'' ?>">
                <?= ucfirst(str_replace('_',' ',$o['payment_status'])) ?>
              </span>
            </div>
            <div class="col-6 col-md-3">
              <?php if ($o['payment_status'] === 'pending' && $o['payment_method'] !== 'cod'): ?>
              <button class="btn btn-sm btn-warning" onclick="openSlipUpload(<?= $o['id'] ?>)">Upload Slip</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

      <?php elseif ($tab === 'order_detail'): ?>
      <?php
        $oid = intval($_GET['id'] ?? 0);
        $oDetail = $db->prepare("SELECT * FROM orders WHERE id=? AND customer_id=?"); $oDetail->execute([$oid,$customerId]); $oDetail = $oDetail->fetch();
        $oItems = $db->prepare("SELECT oi.*, (SELECT image_path FROM product_images WHERE product_id=oi.product_id AND is_primary=1 LIMIT 1) as img FROM order_items oi WHERE oi.order_id=?"); $oItems->execute([$oid]); $oItems = $oItems->fetchAll();
      ?>
      <?php if (!$oDetail): ?>
      <div class="alert alert-danger">Order not found.</div>
      <?php else: ?>
      <div class="d-flex align-items-center gap-2 mb-3">
        <a href="?tab=orders" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <h4 class="fw-bold mb-0">Order #<?= htmlspecialchars($oDetail['order_number']) ?></h4>
        <span class="badge bg-<?= $statusColors[$oDetail['order_status']] ?? 'secondary' ?>"><?= ucfirst($oDetail['order_status']) ?></span>
      </div>

      <!-- Order status progress bar -->
      <?php
        $steps = ['pending','confirmed','processing','shipped','delivered'];
        $currentIdx = array_search($oDetail['order_status'], $steps);
      ?>
      <?php if ($currentIdx !== false): ?>
      <div class="card border-0 shadow-sm mb-3 p-3" style="border-radius:12px">
        <div class="d-flex justify-content-between position-relative">
          <div style="position:absolute;top:16px;left:10%;right:10%;height:4px;background:#e9ecef;z-index:0">
            <div style="width:<?= ($currentIdx / (count($steps)-1)) * 100 ?>%;height:100%;background:var(--primary);border-radius:2px;transition:width .5s"></div>
          </div>
          <?php foreach ($steps as $i => $step): ?>
          <div class="text-center" style="z-index:1;flex:1">
            <div class="rounded-circle mx-auto mb-1 d-flex align-items-center justify-content-center" style="width:32px;height:32px;background:<?= $i<=$currentIdx ? 'var(--primary)' : '#e9ecef' ?>;color:<?= $i<=$currentIdx ? '#fff' : '#aaa' ?>">
              <i class="bi <?= ['bi-clock','bi-check2','bi-gear','bi-truck','bi-house-check'][$i] ?>" style="font-size:.8rem"></i>
            </div>
            <div style="font-size:.7rem;font-weight:<?= $i==$currentIdx?'700':'400' ?>;color:<?= $i<=$currentIdx?'var(--primary)':'#aaa' ?>"><?= ucfirst($step) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="card border-0 shadow-sm mb-3" style="border-radius:12px">
        <div class="card-header bg-white fw-bold">Items Ordered</div>
        <div class="card-body p-0">
          <?php foreach ($oItems as $item): ?>
          <div class="d-flex align-items-center gap-3 p-3 border-bottom">
            <?php if ($item['img']): ?><img src="<?= UPLOAD_URL ?>products/<?= htmlspecialchars($item['img']) ?>" style="width:56px;height:56px;object-fit:cover;border-radius:8px"><?php endif; ?>
            <div class="flex-grow-1">
              <div class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></div>
              <small class="text-muted"><?= htmlspecialchars($item['color_name']) ?> / <?= htmlspecialchars($item['size_label']) ?></small>
            </div>
            <div class="text-end">
              <div class="fw-bold">Rs. <?= number_format($item['total_price']) ?></div>
              <small class="text-muted">Qty: <?= $item['quantity'] ?></small>
            </div>
          </div>
          <?php endforeach; ?>
          <div class="p-3">
            <div class="d-flex justify-content-between"><span>Subtotal</span><span>Rs. <?= number_format($oDetail['subtotal']) ?></span></div>
            <?php if ($oDetail['discount_amount'] > 0): ?><div class="d-flex justify-content-between text-success"><span>Discount</span><span>- Rs. <?= number_format($oDetail['discount_amount']) ?></span></div><?php endif; ?>
            <div class="d-flex justify-content-between"><span>Shipping</span><span>Rs. <?= number_format($oDetail['shipping_fee']) ?></span></div>
            <div class="d-flex justify-content-between fw-bold fs-5 mt-2"><span>Total</span><span>Rs. <?= number_format($oDetail['total_amount']) ?></span></div>
          </div>
        </div>
      </div>

      <?php if ($oDetail['payment_status'] === 'pending' && $oDetail['payment_method'] === 'bank_transfer'): ?>
      <div class="card border-0 shadow-sm mb-3 border-warning" style="border-radius:12px">
        <div class="card-header bg-warning text-dark fw-bold">Upload Payment Slip</div>
        <div class="card-body">
          <p class="small text-muted">Please transfer to our bank account and upload the slip here.</p>
          <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload_slip">
            <input type="hidden" name="order_id" value="<?= $oDetail['id'] ?>">
            <input type="file" name="payment_slip" class="form-control mb-2" accept="image/*" required>
            <button type="submit" class="btn btn-warning fw-bold w-100">Upload Slip</button>
          </form>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <?php elseif ($tab === 'profile'): ?>
      <h4 class="fw-bold mb-3">Profile Settings</h4>
      <div class="card border-0 shadow-sm" style="border-radius:12px">
        <div class="card-body p-4">
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($customer['first_name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($customer['last_name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($customer['email']) ?>" disabled>
                <small class="text-muted">Email cannot be changed</small>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Phone / WhatsApp</label>
                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($customer['phone']) ?>">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Address</label>
                <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">City</label>
                <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($customer['city'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Postal Code</label>
                <input type="text" name="postal_code" class="form-control" value="<?= htmlspecialchars($customer['postal_code'] ?? '') ?>">
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary fw-bold px-5"><i class="bi bi-save me-2"></i>Save Changes</button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Slip Upload Modal -->
<div class="modal fade" id="slipModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_slip">
        <input type="hidden" name="order_id" id="slipOrderId">
        <div class="modal-header"><h6 class="modal-title">Upload Payment Slip</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="file" name="payment_slip" class="form-control" accept="image/*" required>
          <small class="text-muted mt-1 d-block">JPG, PNG or PDF. Max 5MB.</small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning btn-sm fw-bold">Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openSlipUpload(orderId) {
    document.getElementById('slipOrderId').value = orderId;
    new bootstrap.Modal(document.getElementById('slipModal')).show();
}
</script>
<?php renderFooter(); ?>
