<?php
require_once __DIR__ . '/../includes/config.php';
start_secure_session('admin');
requireAdmin();
$db = getDB();

$search = trim($_GET['q'] ?? '');
$where  = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)"; $params = array_fill(0,4,"%$search%"); }

$customers = $db->prepare("SELECT c.*, COUNT(o.id) as order_count, COALESCE(SUM(o.total_amount),0) as total_spent FROM customers c LEFT JOIN orders o ON c.id=o.customer_id AND o.order_status NOT IN ('cancelled','returned') $where GROUP BY c.id ORDER BY c.created_at DESC LIMIT 200");
$customers->execute($params);
$customers = $customers->fetchAll();

include __DIR__ . '/includes/admin_header.php';
?>
<div class="d-flex" id="adminWrapper">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<div class="flex-grow-1 p-4" id="adminContent">

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">Customers</h2>
</div>

<div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
  <div class="card-body py-2">
    <form class="d-flex gap-2" method="GET">
      <input type="search" name="q" class="form-control form-control-sm" style="max-width:280px" placeholder="Search name, email, phone..." value="<?= htmlspecialchars($search) ?>">
      <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search"></i></button>
      <a href="/shop/admin/customers.php" class="btn btn-sm btn-outline-secondary">Reset</a>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:10px">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 small">
        <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Joined</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></td>
            <td><?= htmlspecialchars($c['email']) ?></td>
            <td>
              <?= htmlspecialchars($c['phone']) ?>
              <?php if ($c['whatsapp']): ?>
              <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$c['whatsapp']) ?>" target="_blank" class="ms-1 text-success small"><i class="fab fa-whatsapp"></i></a>
              <?php endif; ?>
            </td>
            <td><span class="badge bg-primary"><?= $c['order_count'] ?></span></td>
            <td class="fw-semibold">Rs. <?= number_format($c['total_spent']) ?></td>
            <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
            <td><span class="badge <?= $c['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($customers)): ?><tr><td colspan="7" class="text-center text-muted py-4">No customers found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</div></div>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
