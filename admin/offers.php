<?php
require_once __DIR__ . '/../includes/config.php';
start_secure_session('admin');
requireAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action_type'] ?? '';
    if ($act === 'save_offer') {
        $id          = intval($_POST['offer_id'] ?? 0);
        $name        = trim($_POST['offer_name'] ?? '');
        $code        = strtoupper(trim($_POST['offer_code'] ?? ''));
        $desc        = trim($_POST['description'] ?? '');
        $type        = $_POST['discount_type'] ?? 'percent';
        $value       = floatval($_POST['discount_value'] ?? 0);
        $minOrder    = floatval($_POST['min_order_amount'] ?? 0);
        $maxDisc     = $_POST['max_discount_amount'] !== '' ? floatval($_POST['max_discount_amount']) : null;
        $appliesTo   = $_POST['applies_to'] ?? 'all';
        $appliesToId = intval($_POST['applies_to_id'] ?? 0) ?: null;
        $usageLimit  = $_POST['usage_limit'] !== '' ? intval($_POST['usage_limit']) : null;
        $startDate   = $_POST['start_date'] ?? date('Y-m-d');
        $endDate     = $_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
        $active      = isset($_POST['is_active']) ? 1 : 0;

        if ($id) {
            $db->prepare("UPDATE offers SET offer_name=?,offer_code=?,description=?,discount_type=?,discount_value=?,min_order_amount=?,max_discount_amount=?,applies_to=?,applies_to_id=?,usage_limit=?,start_date=?,end_date=?,is_active=? WHERE id=?")
               ->execute([$name,$code,$desc,$type,$value,$minOrder,$maxDisc,$appliesTo,$appliesToId,$usageLimit,$startDate,$endDate,$active,$id]);
        } else {
            $db->prepare("INSERT INTO offers (offer_name,offer_code,description,discount_type,discount_value,min_order_amount,max_discount_amount,applies_to,applies_to_id,usage_limit,start_date,end_date,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$name,$code,$desc,$type,$value,$minOrder,$maxDisc,$appliesTo,$appliesToId,$usageLimit,$startDate,$endDate,$active]);
        }
        $_SESSION['flash_success'] = 'Offer saved!';
        header('Location: /shop/admin/offers.php'); exit;
    }
    if ($act === 'delete_offer') {
        $db->prepare("DELETE FROM offers WHERE id=?")->execute([intval($_POST['offer_id'])]);
        $_SESSION['flash_success'] = 'Offer deleted!';
        header('Location: /shop/admin/offers.php'); exit;
    }
    if ($act === 'toggle_offer') {
        $id = intval($_POST['offer_id']); $val = intval($_POST['value']);
        $db->prepare("UPDATE offers SET is_active=? WHERE id=?")->execute([$val,$id]);
        echo json_encode(['success'=>true]); exit;
    }
}

$offers     = $db->query("SELECT o.*, CASE WHEN o.start_date<=CURDATE() AND o.end_date>=CURDATE() THEN 1 ELSE 0 END as is_running FROM offers o ORDER BY o.created_at DESC")->fetchAll();
$categories = $db->query("SELECT id,name FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();

include __DIR__ . '/includes/admin_header.php';
?>
<div class="d-flex" id="adminWrapper">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<div class="flex-grow-1 p-4" id="adminContent">

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0">Offers & Discounts</h2>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#offerModal" onclick="resetOfferForm()"><i class="bi bi-plus-circle me-2"></i>New Offer</button>
</div>

<div class="card border-0 shadow-sm" style="border-radius:10px">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 small">
        <thead class="table-light"><tr><th>Offer Name</th><th>Code</th><th>Discount</th><th>Min Order</th><th>Valid</th><th>Used</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($offers as $o): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($o['offer_name']) ?></div>
              <small class="text-muted"><?= htmlspecialchars($o['description']) ?></small>
            </td>
            <td><code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($o['offer_code']) ?></code></td>
            <td>
              <?php if ($o['discount_type'] === 'percent'): ?>
                <span class="text-success fw-bold"><?= $o['discount_value'] ?>% OFF</span>
                <?php if ($o['max_discount_amount']): ?><br><small class="text-muted">Max Rs.<?= number_format($o['max_discount_amount']) ?></small><?php endif; ?>
              <?php else: ?>
                <span class="text-success fw-bold">Rs.<?= number_format($o['discount_value']) ?> OFF</span>
              <?php endif; ?>
            </td>
            <td>Rs. <?= number_format($o['min_order_amount']) ?></td>
            <td>
              <small><?= date('d M', strtotime($o['start_date'])) ?> – <?= date('d M Y', strtotime($o['end_date'])) ?></small>
              <?php if ($o['is_running']): ?><br><span class="badge bg-success" style="font-size:.65rem">Running</span><?php else: ?><br><span class="badge bg-secondary" style="font-size:.65rem">Inactive</span><?php endif; ?>
            </td>
            <td><?= $o['used_count'] ?><?= $o['usage_limit'] ? '/'.$o['usage_limit'] : '' ?></td>
            <td>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" <?= $o['is_active'] ? 'checked' : '' ?> onchange="toggleOffer(<?= $o['id'] ?>, this.checked ? 1 : 0)">
              </div>
            </td>
            <td>
              <button class="btn btn-sm btn-outline-primary py-0" onclick="editOffer(<?= htmlspecialchars(json_encode($o)) ?>)"><i class="bi bi-pencil"></i></button>
              <form method="POST" style="display:inline" id="delOffer<?= $o['id'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action_type" value="delete_offer">
                <input type="hidden" name="offer_id" value="<?= $o['id'] ?>">
                <button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="confirmDelete('delOffer<?= $o['id'] ?>')"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($offers)): ?><tr><td colspan="8" class="text-center text-muted py-4">No offers yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Offer Modal -->
<div class="modal fade" id="offerModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action_type" value="save_offer">
        <input type="hidden" name="offer_id" id="offerModalId" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="offerModalTitle">New Offer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Offer Name *</label>
              <input type="text" name="offer_name" id="offerName" class="form-control" placeholder="e.g. Aurudu Special" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Promo Code *</label>
              <div class="input-group">
                <input type="text" name="offer_code" id="offerCode" class="form-control text-uppercase fw-bold" placeholder="e.g. AURUDU25" required>
                <button type="button" class="btn btn-outline-secondary" onclick="generateCode()"><i class="bi bi-shuffle"></i></button>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <input type="text" name="description" id="offerDesc" class="form-control" placeholder="Short description shown on website">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Discount Type</label>
              <select name="discount_type" id="offerType" class="form-select" onchange="toggleDiscountFields()">
                <option value="percent">Percentage (%)</option>
                <option value="fixed">Fixed Amount (Rs.)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Discount Value *</label>
              <div class="input-group">
                <span class="input-group-text" id="discSymbol">%</span>
                <input type="number" name="discount_value" id="offerValue" class="form-control" step="0.01" min="0" required>
              </div>
            </div>
            <div class="col-md-4" id="maxDiscountField">
              <label class="form-label">Max Discount (Rs.)</label>
              <input type="number" name="max_discount_amount" id="offerMaxDisc" class="form-control" step="0.01" placeholder="Leave blank for no limit">
            </div>
            <div class="col-md-4">
              <label class="form-label">Min Order Amount (Rs.)</label>
              <input type="number" name="min_order_amount" id="offerMinOrder" class="form-control" value="0" step="0.01">
            </div>
            <div class="col-md-4">
              <label class="form-label">Usage Limit</label>
              <input type="number" name="usage_limit" id="offerLimit" class="form-control" placeholder="Leave blank for unlimited">
            </div>
            <div class="col-md-4">
              <label class="form-label">Applies To</label>
              <select name="applies_to" id="offerAppliesTo" class="form-select">
                <option value="all">All Products</option>
                <option value="category">Specific Category</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Start Date *</label>
              <input type="date" name="start_date" id="offerStart" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">End Date *</label>
              <input type="date" name="end_date" id="offerEnd" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" id="offerActive" checked>
                <label class="form-check-label" for="offerActive">Active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">Save Offer</button>
        </div>
      </form>
    </div>
  </div>
</div>

</div></div>
<script>
function resetOfferForm() {
    document.getElementById('offerModalTitle').textContent = 'New Offer';
    document.getElementById('offerModalId').value = 0;
    document.getElementById('offerName').value = '';
    document.getElementById('offerCode').value = '';
    document.getElementById('offerDesc').value = '';
    document.getElementById('offerType').value = 'percent';
    document.getElementById('offerValue').value = '';
    document.getElementById('offerMaxDisc').value = '';
    document.getElementById('offerMinOrder').value = 0;
    document.getElementById('offerLimit').value = '';
    document.getElementById('offerStart').value = '<?= date('Y-m-d') ?>';
    document.getElementById('offerEnd').value = '<?= date('Y-m-d', strtotime('+30 days')) ?>';
    document.getElementById('offerActive').checked = true;
    toggleDiscountFields();
}
function editOffer(o) {
    document.getElementById('offerModalTitle').textContent = 'Edit Offer';
    document.getElementById('offerModalId').value = o.id;
    document.getElementById('offerName').value = o.offer_name;
    document.getElementById('offerCode').value = o.offer_code;
    document.getElementById('offerDesc').value = o.description || '';
    document.getElementById('offerType').value = o.discount_type;
    document.getElementById('offerValue').value = o.discount_value;
    document.getElementById('offerMaxDisc').value = o.max_discount_amount || '';
    document.getElementById('offerMinOrder').value = o.min_order_amount;
    document.getElementById('offerLimit').value = o.usage_limit || '';
    document.getElementById('offerStart').value = o.start_date;
    document.getElementById('offerEnd').value = o.end_date;
    document.getElementById('offerActive').checked = o.is_active == 1;
    toggleDiscountFields();
    new bootstrap.Modal(document.getElementById('offerModal')).show();
}
function toggleDiscountFields() {
    const type = document.getElementById('offerType').value;
    document.getElementById('discSymbol').textContent = type === 'percent' ? '%' : 'Rs.';
    document.getElementById('maxDiscountField').style.display = type === 'percent' ? 'block' : 'none';
}
function generateCode() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    for (let i = 0; i < 8; i++) code += chars[Math.floor(Math.random() * chars.length)];
    document.getElementById('offerCode').value = code;
}
const CSRF_TOKEN = '<?= csrf_token() ?>';
function toggleOffer(id, val) {
    fetch('/shop/admin/offers.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN},
        body: `action_type=toggle_offer&offer_id=${id}&value=${val}&_csrf=${encodeURIComponent(CSRF_TOKEN)}`
    });
}
</script>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
