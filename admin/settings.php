<?php
require_once __DIR__ . '/../includes/config.php';
start_secure_session('admin');
requireAdmin();

$db = getDB();

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action_type'] ?? '';

    if ($act === 'save_settings') {
        $textFields = ['site_name','site_tagline','contact_whatsapp','contact_email','contact_phone','contact_address','facebook_url','instagram_url','tiktok_url','currency_symbol','shipping_fee','free_shipping_above','meta_description','meta_keywords'];
        $boolFields = ['payhere_enabled','koko_enabled','cod_enabled','whatsapp_orders_enabled','portal_orders_enabled','payhere_sandbox','koko_sandbox'];
        $secretFields = ['payhere_merchant_id','payhere_merchant_secret','koko_api_key','koko_api_secret'];

        foreach (array_merge($textFields, $secretFields) as $key) {
            if (isset($_POST[$key])) {
                $val = trim($_POST[$key]);
                $db->prepare("UPDATE site_settings SET setting_value=? WHERE setting_key=?")->execute([$val, $key]);
            }
        }
        foreach ($boolFields as $key) {
            $val = isset($_POST[$key]) ? '1' : '0';
            $db->prepare("UPDATE site_settings SET setting_value=? WHERE setting_key=?")->execute([$val, $key]);
        }

        // Logo upload
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $res = uploadImage($_FILES['site_logo'], LOGO_UPLOAD_PATH, 'logo');
            if (isset($res['filename'])) {
                $db->prepare("UPDATE site_settings SET setting_value=? WHERE setting_key='site_logo'")->execute([$res['filename']]);
            }
        }

        $_SESSION['flash_success'] = 'Settings saved!';
        header('Location: /shop/admin/settings.php');
        exit;
    }

    if ($act === 'activate_theme') {
        $themeKey = $_POST['theme_key'] ?? '';
        $db->query("UPDATE color_themes SET is_active=0");
        $db->prepare("UPDATE color_themes SET is_active=1 WHERE theme_key=?")->execute([$themeKey]);
        $db->prepare("UPDATE site_settings SET setting_value=? WHERE setting_key='active_theme'")->execute([$themeKey]);
        $_SESSION['flash_success'] = 'Theme activated!';
        header('Location: /shop/admin/settings.php?tab=themes');
        exit;
    }

    if ($act === 'save_theme') {
        $tid = intval($_POST['theme_id'] ?? 0);
        $fields = ['theme_name','primary_color','secondary_color','accent_color','bg_color','text_color','navbar_color','footer_color','button_color','badge_color'];
        $sets = implode(',', array_map(fn($f) => "$f=?", $fields));
        $vals = array_map(fn($f) => trim($_POST[$f] ?? ''), $fields);
        $vals[] = $tid;
        $db->prepare("UPDATE color_themes SET $sets WHERE id=?")->execute($vals);
        $_SESSION['flash_success'] = 'Theme updated!';
        header('Location: /shop/admin/settings.php?tab=themes');
        exit;
    }
}

$settings = getSettings();
$themes   = $db->query("SELECT * FROM color_themes ORDER BY id")->fetchAll();
$activeTab = $_GET['tab'] ?? 'general';

include __DIR__ . '/includes/admin_header.php';
?>
<div class="d-flex" id="adminWrapper">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<div class="flex-grow-1 p-4" id="adminContent">

<h2 class="fw-bold mb-4">Settings</h2>

<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link <?= $activeTab==='general'?'active':'' ?>" href="?tab=general">General</a></li>
  <li class="nav-item"><a class="nav-link <?= $activeTab==='payment'?'active':'' ?>" href="?tab=payment">Payment Gateways</a></li>
  <li class="nav-item"><a class="nav-link <?= $activeTab==='themes'?'active':'' ?>" href="?tab=themes">Color Themes</a></li>
  <li class="nav-item"><a class="nav-link <?= $activeTab==='social'?'active':'' ?>" href="?tab=social">Social & SEO</a></li>
</ul>

<?php if ($activeTab === 'general'): ?>
<form method="POST" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="action_type" value="save_settings">
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
        <div class="card-header bg-white fw-bold">Store Identity</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Store Name</label>
              <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Tagline</label>
              <input type="text" name="site_tagline" class="form-control" value="<?= htmlspecialchars($settings['site_tagline'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Logo</label>
              <?php if ($settings['site_logo']): ?>
              <div class="mb-2"><img src="<?= UPLOAD_URL ?>logos/<?= htmlspecialchars($settings['site_logo']) ?>" height="50" class="border rounded p-1"></div>
              <?php endif; ?>
              <input type="file" name="site_logo" class="form-control" accept="image/*">
              <small class="text-muted">PNG/SVG with transparent background recommended</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Currency Symbol</label>
              <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($settings['currency_symbol'] ?? 'Rs.') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Shipping Fee (Rs.)</label>
              <input type="number" name="shipping_fee" class="form-control" value="<?= htmlspecialchars($settings['shipping_fee'] ?? '350') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Free Shipping Above (Rs.)</label>
              <input type="number" name="free_shipping_above" class="form-control" value="<?= htmlspecialchars($settings['free_shipping_above'] ?? '5000') ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
        <div class="card-header bg-white fw-bold">Contact Details</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><i class="fab fa-whatsapp text-success me-1"></i>WhatsApp Number</label>
              <input type="text" name="contact_whatsapp" class="form-control" placeholder="+94771234567" value="<?= htmlspecialchars($settings['contact_whatsapp'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone Number</label>
              <input type="text" name="contact_phone" class="form-control" value="<?= htmlspecialchars($settings['contact_phone'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Address</label>
              <input type="text" name="contact_address" class="form-control" value="<?= htmlspecialchars($settings['contact_address'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
        <div class="card-header bg-white fw-bold">Order Channels</div>
        <div class="card-body">
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="whatsapp_orders_enabled" id="waEnabled" <?= ($settings['whatsapp_orders_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="waEnabled"><i class="fab fa-whatsapp text-success me-1"></i>Enable WhatsApp Orders button on products</label>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="portal_orders_enabled" id="portalEnabled" <?= ($settings['portal_orders_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="portalEnabled"><i class="bi bi-globe me-1"></i>Enable Portal (Cart/Checkout) Orders</label>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="bi bi-save me-2"></i>Save Settings</button>
    </div>
  </div>
</form>

<?php elseif ($activeTab === 'payment'): ?>
<form method="POST">
  <?= csrf_field() ?>
  <input type="hidden" name="action_type" value="save_settings">
  <div class="row g-3">
    <div class="col-lg-8">
      <!-- COD -->
      <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
        <div class="card-header bg-white fw-bold d-flex justify-content-between">
          Cash on Delivery
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="cod_enabled" id="codEnabled" <?= ($settings['cod_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="codEnabled">Enable</label>
          </div>
        </div>
        <div class="card-body"><p class="text-muted small mb-0">Cash on delivery is always available. No configuration needed.</p></div>
      </div>

      <!-- PayHere -->
      <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
        <div class="card-header bg-white fw-bold d-flex justify-content-between">
          <span><img src="https://www.payhere.lk/images/payhere-logo.png" height="24" onerror="this.style.display='none'"> PayHere</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="payhere_enabled" id="payhereEnabled" <?= ($settings['payhere_enabled'] ?? '0') === '1' ? 'checked' : '' ?> onchange="togglePayhereFields(this.checked)">
            <label class="form-check-label" for="payhereEnabled">Enable</label>
          </div>
        </div>
        <div class="card-body" id="payhereFields" style="display:<?= ($settings['payhere_enabled']??'0')==='1'?'block':'none' ?>">
          <div class="alert alert-info small py-2"><i class="bi bi-info-circle me-1"></i>Get your credentials from <a href="https://www.payhere.lk" target="_blank">payhere.lk</a> merchant dashboard</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Merchant ID</label>
              <input type="text" name="payhere_merchant_id" class="form-control" value="<?= htmlspecialchars($settings['payhere_merchant_id'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Merchant Secret</label>
              <input type="password" name="payhere_merchant_secret" class="form-control" value="<?= htmlspecialchars($settings['payhere_merchant_secret'] ?? '') ?>">
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="payhere_sandbox" id="payhereSandbox" <?= ($settings['payhere_sandbox'] ?? '1') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="payhereSandbox">Sandbox Mode (disable for live)</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Koko -->
      <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
        <div class="card-header bg-white fw-bold d-flex justify-content-between">
          <span>🦁 Koko Pay (Buy Now, Pay Later)</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="koko_enabled" id="kokoEnabled" <?= ($settings['koko_enabled'] ?? '0') === '1' ? 'checked' : '' ?> onchange="toggleKokoFields(this.checked)">
            <label class="form-check-label" for="kokoEnabled">Enable</label>
          </div>
        </div>
        <div class="card-body" id="kokoFields" style="display:<?= ($settings['koko_enabled']??'0')==='1'?'block':'none' ?>">
          <div class="alert alert-info small py-2"><i class="bi bi-info-circle me-1"></i>Get your API credentials from <a href="https://koko.lk" target="_blank">koko.lk</a> merchant portal</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Koko API Key</label>
              <input type="text" name="koko_api_key" class="form-control" value="<?= htmlspecialchars($settings['koko_api_key'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Koko API Secret</label>
              <input type="password" name="koko_api_secret" class="form-control" value="<?= htmlspecialchars($settings['koko_api_secret'] ?? '') ?>">
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="koko_sandbox" id="kokoSandbox" <?= ($settings['koko_sandbox'] ?? '1') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="kokoSandbox">Sandbox Mode</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="bi bi-save me-2"></i>Save Payment Settings</button>
    </div>
  </div>
</form>
<script>
function togglePayhereFields(show) { document.getElementById('payhereFields').style.display = show ? 'block' : 'none'; }
function toggleKokoFields(show)   { document.getElementById('kokoFields').style.display = show ? 'block' : 'none'; }
</script>

<?php elseif ($activeTab === 'themes'): ?>
<div class="row g-3">
  <?php foreach ($themes as $t): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm <?= $t['is_active'] ? 'border border-success border-2' : '' ?>" style="border-radius:12px; overflow:hidden">
      <!-- Theme preview bar -->
      <div style="height:8px; background:linear-gradient(90deg, <?= $t['primary_color'] ?>, <?= $t['secondary_color'] ?>, <?= $t['accent_color'] ?>)"></div>
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <h6 class="fw-bold mb-0"><?= htmlspecialchars($t['theme_name']) ?></h6>
            <small class="text-muted"><?= htmlspecialchars($t['theme_key']) ?></small>
          </div>
          <?php if ($t['is_active']): ?><span class="badge bg-success">Active</span><?php endif; ?>
        </div>
        <!-- Color swatches preview -->
        <div class="d-flex gap-1 mb-3">
          <?php foreach (['primary_color','secondary_color','accent_color','navbar_color','button_color','badge_color'] as $cf): ?>
          <span style="width:22px;height:22px;border-radius:4px;background:<?= $t[$cf] ?>;border:1px solid rgba(0,0,0,0.1)" title="<?= $cf ?>"></span>
          <?php endforeach; ?>
        </div>

        <!-- Edit form -->
        <form method="POST" class="mb-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action_type" value="save_theme">
          <input type="hidden" name="theme_id" value="<?= $t['id'] ?>">
          <input type="hidden" name="theme_name" value="<?= htmlspecialchars($t['theme_name']) ?>">
          <div class="row g-1 mb-2">
            <?php $colorFields = ['primary_color'=>'Primary','secondary_color'=>'Secondary','accent_color'=>'Accent','navbar_color'=>'Navbar','footer_color'=>'Footer','button_color'=>'Button','badge_color'=>'Badge','bg_color'=>'Background','text_color'=>'Text']; ?>
            <?php foreach ($colorFields as $field => $label): ?>
            <div class="col-4 text-center">
              <label class="form-label small mb-0"><?= $label ?></label>
              <input type="color" name="<?= $field ?>" class="form-control form-control-color w-100" value="<?= $t[$field] ?>" style="height:28px; padding:2px">
            </div>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-sm btn-outline-secondary w-100 mb-1">Save Colors</button>
        </form>

        <?php if (!$t['is_active']): ?>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action_type" value="activate_theme">
          <input type="hidden" name="theme_key" value="<?= $t['theme_key'] ?>">
          <button type="submit" class="btn btn-sm btn-success w-100">Activate Theme</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif ($activeTab === 'social'): ?>
<form method="POST">
  <?= csrf_field() ?>
  <input type="hidden" name="action_type" value="save_settings">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
      <div class="card-header bg-white fw-bold">Social Media Links</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label"><i class="fab fa-facebook me-1 text-primary"></i>Facebook URL</label>
            <input type="url" name="facebook_url" class="form-control" value="<?= htmlspecialchars($settings['facebook_url'] ?? '') ?>" placeholder="https://facebook.com/yourpage">
          </div>
          <div class="col-md-6">
            <label class="form-label"><i class="fab fa-instagram me-1" style="color:#e1306c"></i>Instagram URL</label>
            <input type="url" name="instagram_url" class="form-control" value="<?= htmlspecialchars($settings['instagram_url'] ?? '') ?>" placeholder="https://instagram.com/yourprofile">
          </div>
          <div class="col-md-6">
            <label class="form-label"><i class="fab fa-tiktok me-1"></i>TikTok URL</label>
            <input type="url" name="tiktok_url" class="form-control" value="<?= htmlspecialchars($settings['tiktok_url'] ?? '') ?>" placeholder="https://tiktok.com/@yourprofile">
          </div>
        </div>
      </div>
    </div>
    <div class="card border-0 shadow-sm mb-3" style="border-radius:10px">
      <div class="card-header bg-white fw-bold">SEO</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Meta Description</label>
          <textarea name="meta_description" class="form-control" rows="2"><?= htmlspecialchars($settings['meta_description'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Meta Keywords</label>
          <input type="text" name="meta_keywords" class="form-control" value="<?= htmlspecialchars($settings['meta_keywords'] ?? '') ?>">
        </div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="bi bi-save me-2"></i>Save</button>
  </div>
</form>
<?php endif; ?>

</div></div>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
