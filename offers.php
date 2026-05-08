<?php
require_once __DIR__ . '/includes/config.php';
start_secure_session('customer');
require_once __DIR__ . '/includes/theme.php';

$db = getDB();
$settings = getSettings();

$active = $db->query("SELECT * FROM offers
    WHERE is_active=1
      AND (start_date IS NULL OR start_date<=CURDATE())
      AND (end_date IS NULL OR end_date>=CURDATE())
    ORDER BY end_date ASC")->fetchAll();

$expired = $db->query("SELECT * FROM offers
    WHERE is_active=1 AND end_date<CURDATE()
    ORDER BY end_date DESC LIMIT 6")->fetchAll();

$currency = $settings['currency_symbol'] ?? 'Rs.';

renderHead('Active Offers & Promo Codes');
renderNavbar();
?>

<div class="container my-4">
  <div class="text-center mb-4">
    <h1 class="fw-bold" style="font-family:'Playfair Display',serif;color:var(--primary)">
      <i class="bi bi-tag-fill" style="color:var(--accent)"></i> Active Offers
    </h1>
    <p class="text-muted">Use these promo codes at checkout to save more!</p>
  </div>

  <?php if (empty($active)): ?>
  <div class="text-center py-5">
    <i class="bi bi-tag fs-1 text-muted"></i>
    <h5 class="mt-3">No active offers right now</h5>
    <p class="text-muted">Check back soon for new deals!</p>
    <a href="/shop/shop.php" class="btn btn-primary">Continue Shopping</a>
  </div>
  <?php else: ?>
  <div class="row g-3">
    <?php foreach ($active as $o): ?>
    <?php
      $discountText = $o['discount_type'] === 'percent'
          ? rtrim(rtrim(number_format($o['discount_value'], 2), '0'), '.') . '% OFF'
          : $currency . ' ' . number_format($o['discount_value'], 2) . ' OFF';
      $endDate = $o['end_date'] ? date('d M Y', strtotime($o['end_date'])) : 'No end date';
      $daysLeft = $o['end_date'] ? max(0, (strtotime($o['end_date']) - time()) / 86400) : null;
    ?>
    <div class="col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden">
        <div style="background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:22px 18px">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <small class="text-white-50">Save</small>
              <h3 class="fw-bold mb-0"><?= htmlspecialchars($discountText) ?></h3>
            </div>
            <i class="bi bi-tag-fill fs-2" style="color:var(--accent)"></i>
          </div>
        </div>
        <div class="card-body">
          <h5 class="fw-bold mb-1"><?= htmlspecialchars($o['offer_name']) ?></h5>
          <?php if ($o['description']): ?>
          <p class="text-muted small mb-2"><?= htmlspecialchars($o['description']) ?></p>
          <?php endif; ?>
          <ul class="list-unstyled small mb-3">
            <?php if ($o['min_order_amount'] > 0): ?>
            <li><i class="bi bi-cart-check me-2 text-success"></i>Min order: <?= $currency ?> <?= number_format($o['min_order_amount']) ?></li>
            <?php endif; ?>
            <?php if ($o['max_discount_amount']): ?>
            <li><i class="bi bi-arrow-up-circle me-2 text-warning"></i>Max discount: <?= $currency ?> <?= number_format($o['max_discount_amount']) ?></li>
            <?php endif; ?>
            <li><i class="bi bi-calendar-event me-2 text-primary"></i>Valid till: <?= $endDate ?>
              <?php if ($daysLeft !== null && $daysLeft <= 7): ?>
              <span class="badge bg-danger ms-1"><?= ceil($daysLeft) ?> day(s) left</span>
              <?php endif; ?>
            </li>
            <?php if ($o['usage_limit']): ?>
            <li><i class="bi bi-people me-2 text-secondary"></i><?= max(0, $o['usage_limit'] - $o['used_count']) ?> uses remaining</li>
            <?php endif; ?>
          </ul>
          <div class="border border-2 border-dashed rounded p-2 text-center mb-2" style="border-style:dashed!important;background:var(--bg)">
            <small class="text-muted d-block">Promo Code</small>
            <div class="d-flex justify-content-center align-items-center gap-2">
              <code class="fw-bold fs-5" style="color:var(--primary)"><?= htmlspecialchars($o['offer_code']) ?></code>
              <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyCode('<?= htmlspecialchars($o['offer_code']) ?>', this)">
                <i class="bi bi-clipboard"></i>
              </button>
            </div>
          </div>
          <a href="/shop/shop.php" class="btn btn-primary w-100">Shop Now</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($expired)): ?>
  <hr class="my-5">
  <h5 class="fw-bold text-muted mb-3"><i class="bi bi-clock-history me-2"></i>Recently Expired</h5>
  <div class="row g-2">
    <?php foreach ($expired as $o): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm" style="border-radius:10px;opacity:0.6">
        <div class="card-body py-2 d-flex justify-content-between align-items-center">
          <div>
            <span class="fw-semibold"><?= htmlspecialchars($o['offer_name']) ?></span><br>
            <small class="text-muted">Ended <?= date('d M Y', strtotime($o['end_date'])) ?></small>
          </div>
          <span class="badge bg-secondary">Expired</span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script>
function copyCode(code, btn) {
    navigator.clipboard.writeText(code).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2"></i>';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        if (typeof toastr !== 'undefined') toastr.success('Code copied: ' + code);
        setTimeout(() => {
            btn.innerHTML = original;
            btn.classList.add('btn-outline-primary');
            btn.classList.remove('btn-success');
        }, 1500);
    });
}
</script>

<?php renderFooter(); ?>
