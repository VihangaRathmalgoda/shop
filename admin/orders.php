<?php
require_once __DIR__ . '/../includes/config.php';
start_secure_session('admin');
requireAdmin();

$db = getDB();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action_type'] ?? '';
    if ($act === 'update_status') {
        $orderId      = intval($_POST['order_id'] ?? 0);
        $orderStatus  = $_POST['order_status'] ?? '';
        $paymentStatus = $_POST['payment_status'] ?? '';
        $adminNotes   = trim($_POST['admin_notes'] ?? '');
        $validOS = ['pending','confirmed','processing','shipped','delivered','cancelled','returned'];
        $validPS = ['pending','slip_uploaded','verified','failed','refunded'];
        if (in_array($orderStatus, $validOS) && in_array($paymentStatus, $validPS)) {
            $db->prepare("UPDATE orders SET order_status=?, payment_status=?, admin_notes=?, updated_at=NOW() WHERE id=?")->execute([$orderStatus, $paymentStatus, $adminNotes, $orderId]);
            $db->prepare("INSERT INTO order_status_history (order_id, status, notes, changed_by) VALUES (?,?,?,?)")->execute([$orderId, $orderStatus, $adminNotes, 'admin']);
            $_SESSION['flash_success'] = 'Order updated!';
        }
        header('Location: /shop/admin/orders.php');
        exit;
    }
}

// Filters
$statusFilter  = $_GET['status'] ?? '';
$payFilter     = $_GET['payment'] ?? '';
$search        = trim($_GET['q'] ?? '');
$validStatuses = ['pending','confirmed','processing','shipped','delivered','cancelled','returned'];

$where  = "WHERE 1=1";
$params = [];
if ($statusFilter && in_array($statusFilter, $validStatuses)) { $where .= " AND o.order_status=?"; $params[] = $statusFilter; }
if ($payFilter) { $where .= " AND o.payment_status=?"; $params[] = $payFilter; }
if ($search) { $where .= " AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $db->prepare("SELECT o.*, COUNT(oi.id) as item_count FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id $where GROUP BY o.id ORDER BY o.created_at DESC LIMIT 200");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statusColors = ['pending'=>'warning','confirmed'=>'info','processing'=>'primary','shipped'=>'secondary','delivered'=>'success','cancelled'=>'danger','returned'=>'dark'];
$payColors    = ['pending'=>'warning','slip_uploaded'=>'info','verified'=>'success','failed'=>'danger','refunded'=>'secondary'];

include __DIR__ . '/includes/admin_header.php';
?>
<div class="d-flex" id="adminWrapper">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<div class="flex-grow-1 p-4" id="adminContent">

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">Orders <?php if ($statusFilter): ?><small class="fs-6 fw-normal text-muted">— <?= ucfirst($statusFilter) ?></small><?php endif; ?></h2>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
  <div class="card-body py-2">
    <form class="d-flex flex-wrap gap-2 align-items-center" method="GET">
      <input type="search" name="q" class="form-control form-control-sm" style="max-width:220px" placeholder="Order #, customer, phone..." value="<?= htmlspecialchars($search) ?>">
      <select name="status" class="form-select form-select-sm" style="max-width:160px" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <?php foreach ($validStatuses as $s): ?><option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
      </select>
      <select name="payment" class="form-select form-select-sm" style="max-width:160px" onchange="this.form.submit()">
        <option value="">All Payments</option>
        <option value="pending" <?= $payFilter==='pending'?'selected':'' ?>>Payment Pending</option>
        <option value="slip_uploaded" <?= $payFilter==='slip_uploaded'?'selected':'' ?>>Slip Uploaded</option>
        <option value="verified" <?= $payFilter==='verified'?'selected':'' ?>>Verified</option>
      </select>
      <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search"></i></button>
      <a href="/shop/admin/orders.php" class="btn btn-sm btn-outline-secondary">Reset</a>
    </form>
  </div>
</div>

<!-- Bulk status update modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action_type" value="update_status">
        <input type="hidden" name="order_id" id="modalOrderId">
        <div class="modal-header">
          <h5 class="modal-title">Update Order <span id="modalOrderNum"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Order Status</label>
            <select name="order_status" id="modalOrderStatus" class="form-select">
              <?php foreach ($validStatuses as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Payment Status</label>
            <select name="payment_status" id="modalPayStatus" class="form-select">
              <option value="pending">Pending</option>
              <option value="slip_uploaded">Slip Uploaded</option>
              <option value="verified">Verified</option>
              <option value="failed">Failed</option>
              <option value="refunded">Refunded</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Admin Notes</label>
            <textarea name="admin_notes" id="modalAdminNotes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Order</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:10px">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 small">
        <thead class="table-light">
          <tr><th>Order #</th><th>Date</th><th>Customer</th><th>Source</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td class="fw-bold"><?= htmlspecialchars($o['order_number']) ?></td>
            <td><?= date('d M H:i', strtotime($o['created_at'])) ?></td>
            <td>
              <div><?= htmlspecialchars($o['customer_name']) ?></div>
              <small class="text-muted"><?= htmlspecialchars($o['customer_phone']) ?></small>
              <?php if ($o['customer_whatsapp']): ?>
              <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$o['customer_whatsapp']) ?>" target="_blank" class="ms-1 text-success"><i class="fab fa-whatsapp"></i></a>
              <?php endif; ?>
            </td>
            <td>
              <?php $srcIcons = ['whatsapp'=>'<i class="fab fa-whatsapp text-success"></i>','portal'=>'<i class="bi bi-globe text-primary"></i>','admin'=>'<i class="bi bi-person-gear text-warning"></i>']; ?>
              <?= $srcIcons[$o['order_source']] ?? $o['order_source'] ?>
            </td>
            <td><span class="badge bg-secondary"><?= $o['item_count'] ?> item(s)</span></td>
            <td class="fw-bold">Rs. <?= number_format($o['total_amount']) ?></td>
            <td>
              <span class="badge <?= isset($payColors[$o['payment_status']]) ? 'bg-'.$payColors[$o['payment_status']] : 'bg-secondary' ?> <?= $o['payment_status']==='pending'?'text-dark':'' ?>">
                <?= ucfirst(str_replace('_',' ',$o['payment_status'])) ?>
              </span>
              <?php if ($o['payment_slip']): ?>
              <a href="<?= UPLOAD_URL ?>slips/<?= htmlspecialchars($o['payment_slip']) ?>" target="_blank" class="ms-1 small" title="View slip"><i class="bi bi-image"></i></a>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge status-<?= $o['order_status'] ?>">
                <?= ucfirst($o['order_status']) ?>
              </span>
            </td>
            <td>
              <div class="d-flex gap-1">
                <a href="/shop/admin/order_detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary py-0" title="View"><i class="bi bi-eye"></i></a>
                <button class="btn btn-sm btn-outline-secondary py-0" title="Quick Update"
                  onclick="openUpdateModal(<?= $o['id'] ?>, '<?= htmlspecialchars($o['order_number']) ?>', '<?= $o['order_status'] ?>', '<?= $o['payment_status'] ?>', '<?= htmlspecialchars(addslashes($o['admin_notes'] ?? '')) ?>')">
                  <i class="bi bi-pencil-square"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($orders)): ?><tr><td colspan="9" class="text-center text-muted py-5">No orders found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</div></div>

<script>
function openUpdateModal(id, num, orderStatus, payStatus, notes) {
    document.getElementById('modalOrderId').value = id;
    document.getElementById('modalOrderNum').textContent = '#' + num;
    document.getElementById('modalOrderStatus').value = orderStatus;
    document.getElementById('modalPayStatus').value = payStatus;
    document.getElementById('modalAdminNotes').value = notes;
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
