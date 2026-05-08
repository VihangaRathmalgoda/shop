<?php
// admin/index.php - Admin Dashboard
require_once __DIR__ . '/../includes/config.php';
start_secure_session('admin');
requireAdmin();

$db = getDB();

// Dashboard stats
$totalOrders  = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = $db->query("SELECT COUNT(*) FROM orders WHERE order_status='pending'")->fetchColumn();
$todayOrders  = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$totalRevenue = $db->query("SELECT SUM(total_amount) FROM orders WHERE order_status NOT IN ('cancelled','returned')")->fetchColumn();
$totalProducts = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$lowStock = $db->query("SELECT COUNT(DISTINCT product_id) FROM product_stock WHERE quantity>0 AND quantity<=5")->fetchColumn();
$totalCustomers = $db->query("SELECT COUNT(*) FROM customers WHERE is_active=1")->fetchColumn();
$recentOrders = $db->query("SELECT o.*, c.first_name, c.last_name FROM orders o LEFT JOIN customers c ON o.customer_id=c.id ORDER BY o.created_at DESC LIMIT 8")->fetchAll();
$monthlyRevenue = $db->query("SELECT DATE_FORMAT(created_at,'%b') as month, SUM(total_amount) as rev FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND order_status NOT IN ('cancelled','returned') GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY created_at")->fetchAll();

include __DIR__ . '/includes/admin_header.php';
?>

<div class="d-flex" id="adminWrapper">
  <?php include __DIR__ . '/includes/admin_sidebar.php'; ?>

  <div class="flex-grow-1 p-4" id="adminContent">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="fw-bold mb-0">Dashboard</h2>
        <small class="text-muted">Welcome back, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></small>
      </div>
      <span class="text-muted small"><?= date('l, d F Y') ?></span>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid var(--primary)!important; border-radius:10px">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <p class="text-muted small mb-0">Total Orders</p>
                <h3 class="fw-bold mb-0"><?= number_format($totalOrders) ?></h3>
              </div>
              <i class="bi bi-bag-check fs-1 text-muted"></i>
            </div>
            <small class="text-success"><i class="bi bi-arrow-up"></i> <?= $todayOrders ?> today</small>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #f39c12!important; border-radius:10px">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <p class="text-muted small mb-0">Pending Orders</p>
                <h3 class="fw-bold mb-0"><?= number_format($pendingOrders) ?></h3>
              </div>
              <i class="bi bi-clock fs-1 text-warning"></i>
            </div>
            <a href="/shop/admin/orders.php?status=pending" class="small text-warning">View pending →</a>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #27ae60!important; border-radius:10px">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <p class="text-muted small mb-0">Total Revenue</p>
                <h3 class="fw-bold mb-0">Rs. <?= number_format($totalRevenue ?? 0) ?></h3>
              </div>
              <i class="bi bi-currency-dollar fs-1 text-success"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #e74c3c!important; border-radius:10px">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <p class="text-muted small mb-0">Low Stock Items</p>
                <h3 class="fw-bold mb-0"><?= $lowStock ?></h3>
              </div>
              <i class="bi bi-exclamation-triangle fs-1 text-danger"></i>
            </div>
            <a href="/shop/admin/products.php?filter=lowstock" class="small text-danger">View items →</a>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <!-- Recent Orders -->
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:10px">
          <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
            <h6 class="fw-bold mb-0">Recent Orders</h6>
            <a href="/shop/admin/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover mb-0 small">
                <thead class="table-light"><tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($recentOrders as $o): ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($o['order_number']) ?></td>
                  <td><?= htmlspecialchars($o['customer_name']) ?></td>
                  <td>Rs. <?= number_format($o['total_amount']) ?></td>
                  <td><span class="badge <?= $o['payment_status']==='verified'?'bg-success':($o['payment_status']==='pending'?'bg-warning text-dark':'bg-secondary') ?>"><?= ucfirst($o['payment_status']) ?></span></td>
                  <td><?php
                    $statusColors = ['pending'=>'warning','confirmed'=>'info','processing'=>'primary','shipped'=>'secondary','delivered'=>'success','cancelled'=>'danger','returned'=>'dark'];
                    $sc = $statusColors[$o['order_status']] ?? 'secondary';
                  ?><span class="badge bg-<?= $sc ?>"><?= ucfirst($o['order_status']) ?></span></td>
                  <td><a href="/shop/admin/order_detail.php?id=<?= $o['id'] ?>" class="btn btn-xs btn-outline-primary btn-sm py-0">View</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
          <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0">Quick Actions</h6></div>
          <div class="card-body d-grid gap-2">
            <a href="/shop/admin/products.php?action=add" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-2"></i>Add New Product</a>
            <a href="/shop/admin/orders.php?status=pending" class="btn btn-warning btn-sm text-dark"><i class="bi bi-clock me-2"></i>Process Pending Orders</a>
            <a href="/shop/admin/banners.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-image me-2"></i>Manage Banners</a>
            <a href="/shop/admin/offers.php?action=add" class="btn btn-outline-success btn-sm"><i class="bi bi-tag me-2"></i>Create Offer</a>
            <a href="/shop/admin/settings.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-gear me-2"></i>Settings</a>
          </div>
        </div>
        <div class="card border-0 shadow-sm" style="border-radius:10px">
          <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0">Store Overview</h6></div>
          <div class="card-body">
            <div class="d-flex justify-content-between mb-2"><span class="small">Products Active</span><strong><?= $totalProducts ?></strong></div>
            <div class="d-flex justify-content-between mb-2"><span class="small">Customers</span><strong><?= $totalCustomers ?></strong></div>
            <div class="d-flex justify-content-between mb-2"><span class="small">Today's Orders</span><strong><?= $todayOrders ?></strong></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
