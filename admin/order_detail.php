<?php
require_once __DIR__ . '/../includes/config.php';
start_secure_session('admin');
requireAdmin();
$db = getDB();

$orderId = intval($_GET['id'] ?? 0);
if (!$orderId) { header('Location: /shop/admin/orders.php'); exit; }

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action_type'] ?? '';
    if ($act === 'update_order') {
        $orderStatus   = $_POST['order_status'] ?? '';
        $paymentStatus = $_POST['payment_status'] ?? '';
        $adminNotes    = trim($_POST['admin_notes'] ?? '');
        $validOS = ['pending','confirmed','processing','shipped','delivered','cancelled','returned'];
        $validPS = ['pending','slip_uploaded','verified','failed','refunded'];
        if (in_array($orderStatus, $validOS) && in_array($paymentStatus, $validPS)) {
            $db->prepare("UPDATE orders SET order_status=?,payment_status=?,admin_notes=?,updated_at=NOW() WHERE id=?")
               ->execute([$orderStatus,$paymentStatus,$adminNotes,$orderId]);
            $db->prepare("INSERT INTO order_status_history (order_id,status,notes,changed_by) VALUES (?,?,?,'admin')")
               ->execute([$orderId,$orderStatus,$adminNotes]);
            $_SESSION['flash_success'] = 'Order updated!';
        }
        header("Location: /shop/admin/order_detail.php?id=$orderId"); exit;
    }
}

$order = $db->prepare("SELECT o.*, c.first_name, c.last_name, c.email as c_email FROM orders o LEFT JOIN customers c ON o.customer_id=c.id WHERE o.id=?");
$order->execute([$orderId]);
$order = $order->fetch();
if (!$order) { header('Location: /shop/admin/orders.php'); exit; }

$items   = $db->prepare("SELECT oi.*, p.product_code, (SELECT image_path FROM product_images WHERE product_id=oi.product_id AND is_primary=1 LIMIT 1) as img FROM order_items oi LEFT JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?");
$items->execute([$orderId]);
$items = $items->fetchAll();

$history = $db->prepare("SELECT * FROM order_status_history WHERE order_id=? ORDER BY created_at DESC");
$history->execute([$orderId]);
$history = $history->fetchAll();

$statusColors = ['pending'=>'warning','confirmed'=>'info','processing'=>'primary','shipped'=>'secondary','delivered'=>'success','cancelled'=>'danger','returned'=>'dark'];
$validStatuses = ['pending','confirmed','processing','shipped','delivered','cancelled','returned'];

include __DIR__ . '/includes/admin_header.php';
?>
<div class="d-flex" id="adminWrapper">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<div class="flex-grow-1 p-4" id="adminContent">

<div class="d-flex align-items-center mb-4 gap-3">
  <a href="/shop/admin/orders.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h2 class="fw-bold mb-0">Order #<?= htmlspecialchars($order['order_number']) ?></h2>
    <small class="text-muted"><?= date('d F Y, H:i', strtotime($order['created_at'])) ?> &bull; via <?= ucfirst($order['order_source']) ?></small>
  </div>
  <span class="badge ms-auto status-<?= $order['order_status'] ?> fs-6"><?= ucfirst($order['order_status']) ?></span>
</div>

<div class="row g-3">
  <!-- Left: Items + History -->
  <div class="col-lg-8">
    <!-- Items -->
    <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
      <div class="card-header bg-white fw-bold">Order Items</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead class="table-light"><tr><th>Product</th><th>Color</th><th>Size</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
            <tbody>
              <?php foreach ($items as $item): ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <?php if ($item['img']): ?>
                    <img src="<?= UPLOAD_URL ?>products/<?= htmlspecialchars($item['img']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px">
                    <?php endif; ?>
                    <div>
                      <div class="fw-semibold small"><?= htmlspecialchars($item['product_name']) ?></div>
                      <small class="text-muted"><?= htmlspecialchars($item['product_code']) ?></small>
                    </div>
                  </div>
                </td>
                <td><?= htmlspecialchars($item['color_name']) ?></td>
                <td><?= htmlspecialchars($item['size_label']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td>Rs. <?= number_format($item['unit_price'], 2) ?></td>
                <td class="fw-bold">Rs. <?= number_format($item['total_price'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
              <tr><td colspan="5" class="text-end">Subtotal</td><td class="fw-bold">Rs. <?= number_format($order['subtotal'], 2) ?></td></tr>
              <?php if ($order['discount_amount'] > 0): ?>
              <tr><td colspan="5" class="text-end text-success">Discount (<?= htmlspecialchars($order['offer_code']) ?>)</td><td class="fw-bold text-success">- Rs. <?= number_format($order['discount_amount'], 2) ?></td></tr>
              <?php endif; ?>
              <tr><td colspan="5" class="text-end">Shipping</td><td class="fw-bold">Rs. <?= number_format($order['shipping_fee'], 2) ?></td></tr>
              <tr class="table-success"><td colspan="5" class="text-end fw-bold">Total</td><td class="fw-bold fs-5">Rs. <?= number_format($order['total_amount'], 2) ?></td></tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- Payment slip -->
    <?php if ($order['payment_slip']): ?>
    <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
      <div class="card-header bg-white fw-bold">Payment Slip</div>
      <div class="card-body text-center">
        <img src="<?= UPLOAD_URL ?>slips/<?= htmlspecialchars($order['payment_slip']) ?>" style="max-height:300px;border-radius:8px;max-width:100%">
        <br><a href="<?= UPLOAD_URL ?>slips/<?= htmlspecialchars($order['payment_slip']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="bi bi-download me-1"></i>View Full</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Status History -->
    <?php if (!empty($history)): ?>
    <div class="card border-0 shadow-sm" style="border-radius:10px">
      <div class="card-header bg-white fw-bold">Status History</div>
      <div class="card-body">
        <div class="timeline">
          <?php foreach ($history as $h): ?>
          <div class="d-flex gap-3 mb-3">
            <div class="flex-shrink-0">
              <span class="badge status-<?= $h['status'] ?> rounded-pill"><?= ucfirst($h['status']) ?></span>
            </div>
            <div class="flex-grow-1">
              <?php if ($h['notes']): ?><p class="mb-0 small"><?= htmlspecialchars($h['notes']) ?></p><?php endif; ?>
              <small class="text-muted"><?= date('d M Y H:i', strtotime($h['created_at'])) ?> by <?= ucfirst($h['changed_by']) ?></small>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right sidebar -->
  <div class="col-lg-4">
    <!-- Update Status -->
    <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
      <div class="card-header bg-white fw-bold">Update Order</div>
      <div class="card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action_type" value="update_order">
          <div class="mb-3">
            <label class="form-label fw-semibold">Order Status</label>
            <select name="order_status" class="form-select">
              <?php foreach ($validStatuses as $s): ?>
              <option value="<?= $s ?>" <?= $order['order_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Payment Status</label>
            <select name="payment_status" class="form-select">
              <option value="pending" <?= $order['payment_status']==='pending'?'selected':'' ?>>Pending</option>
              <option value="slip_uploaded" <?= $order['payment_status']==='slip_uploaded'?'selected':'' ?>>Slip Uploaded</option>
              <option value="verified" <?= $order['payment_status']==='verified'?'selected':'' ?>>Verified</option>
              <option value="failed" <?= $order['payment_status']==='failed'?'selected':'' ?>>Failed</option>
              <option value="refunded" <?= $order['payment_status']==='refunded'?'selected':'' ?>>Refunded</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Notes</label>
            <textarea name="admin_notes" class="form-control" rows="3" placeholder="Internal notes..."><?= htmlspecialchars($order['admin_notes'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="bi bi-save me-2"></i>Update</button>
        </form>
      </div>
    </div>

    <!-- Customer Info -->
    <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
      <div class="card-header bg-white fw-bold">Customer</div>
      <div class="card-body small">
        <p class="fw-bold mb-1"><?= htmlspecialchars($order['customer_name']) ?></p>
        <?php if ($order['customer_email']): ?><p class="mb-1"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($order['customer_email']) ?></p><?php endif; ?>
        <?php if ($order['customer_phone']): ?><p class="mb-1"><i class="bi bi-phone me-1"></i><?= htmlspecialchars($order['customer_phone']) ?></p><?php endif; ?>
        <?php if ($order['customer_whatsapp']): ?>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$order['customer_whatsapp']) ?>" target="_blank" class="btn btn-sm w-100 mt-2" style="background:#25d366;color:#fff"><i class="fab fa-whatsapp me-1"></i>Message on WhatsApp</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Delivery Info -->
    <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
      <div class="card-header bg-white fw-bold">Delivery Address</div>
      <div class="card-body small">
        <p class="mb-1"><?= nl2br(htmlspecialchars($order['delivery_address'])) ?></p>
        <?php if ($order['delivery_city']): ?><p class="mb-1"><?= htmlspecialchars($order['delivery_city']) ?><?= $order['delivery_postal'] ? ', '.$order['delivery_postal'] : '' ?></p><?php endif; ?>
      </div>
    </div>

    <!-- Payment Info -->
    <div class="card border-0 shadow-sm" style="border-radius:10px">
      <div class="card-header bg-white fw-bold">Payment</div>
      <div class="card-body small">
        <p class="mb-1"><strong>Method:</strong> <?= ucfirst(str_replace('_',' ',$order['payment_method'])) ?></p>
        <p class="mb-1"><strong>Status:</strong> <span class="badge bg-<?= ['pending'=>'warning','verified'=>'success','failed'=>'danger'][$order['payment_status']] ?? 'secondary' ?>"><?= ucfirst(str_replace('_',' ',$order['payment_status'])) ?></span></p>
        <?php if ($order['payment_id']): ?><p class="mb-1"><strong>Ref:</strong> <?= htmlspecialchars($order['payment_id']) ?></p><?php endif; ?>
      </div>
    </div>
  </div>
</div>

</div></div>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
