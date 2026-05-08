<?php
// includes/theme.php - Dynamic theme CSS + shared HTML head
function renderThemeCSS($theme) {
    return "
    :root {
        --primary:    {$theme['primary_color']};
        --secondary:  {$theme['secondary_color']};
        --accent:     {$theme['accent_color']};
        --bg:         {$theme['bg_color']};
        --text:       {$theme['text_color']};
        --navbar-bg:  {$theme['navbar_color']};
        --footer-bg:  {$theme['footer_color']};
        --btn-color:  {$theme['button_color']};
        --badge:      {$theme['badge_color']};
    }";
}

function renderHead($title = '', $extraCSS = '') {
    $settings = getSettings();
    $theme    = getActiveTheme();
    $siteName = $settings['site_name'] ?? 'Fashion Store';
    $pageTitle = $title ? "$title | $siteName" : $siteName;
    $themeCSS = renderThemeCSS($theme);
    $csrf     = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    $metaDesc = htmlspecialchars($settings['meta_description'] ?? '', ENT_QUOTES, 'UTF-8');
    $metaKw   = htmlspecialchars($settings['meta_keywords'] ?? '', ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="description" content="{$metaDesc}">
<meta name="keywords" content="{$metaKw}">
<meta name="csrf-token" content="{$csrf}">
<title>$pageTitle</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
$themeCSS
* { box-sizing: border-box; }
body { font-family: 'Poppins', sans-serif; background-color: var(--bg); color: var(--text); min-height: 100vh; }
a { color: var(--secondary); text-decoration: none; }
a:hover { color: var(--primary); }

/* Navbar */
.navbar { background-color: var(--navbar-bg) !important; box-shadow: 0 2px 10px rgba(0,0,0,0.15); padding: 12px 0; }
.navbar-brand { font-family: 'Playfair Display', serif; font-size: 1.6rem; color: #fff !important; font-weight: 700; }
.navbar-brand img { max-height: 50px; }
.nav-link { color: rgba(255,255,255,0.85) !important; font-weight: 500; transition: color .2s; font-size: .92rem; }
.nav-link:hover, .nav-link.active { color: var(--accent) !important; }

/* Buttons */
.btn-primary { background-color: var(--btn-color); border-color: var(--btn-color); }
.btn-primary:hover { background-color: var(--primary); border-color: var(--primary); }
.btn-outline-primary { color: var(--btn-color); border-color: var(--btn-color); }
.btn-outline-primary:hover { background-color: var(--btn-color); color: #fff; }
.btn-accent { background-color: var(--accent); border-color: var(--accent); color: #fff; }
.btn-accent:hover { filter: brightness(90%); color: #fff; }

/* Product Cards */
.product-card { border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); transition: transform .25s, box-shadow .25s; height: 100%; }
.product-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
.product-card .card-img-wrap { position: relative; overflow: hidden; height: 260px; background: #f0f0f0; }
.product-card .card-img-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s; }
.product-card:hover .card-img-wrap img { transform: scale(1.06); }
.badge-sale { position: absolute; top: 10px; left: 10px; background: var(--secondary); color: #fff; font-size: .72rem; padding: 4px 10px; border-radius: 20px; font-weight: 600; }
.badge-new { position: absolute; top: 10px; right: 10px; background: var(--badge); color: #fff; font-size: .72rem; padding: 4px 10px; border-radius: 20px; font-weight: 600; }
.badge-oos { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; font-weight: 700; letter-spacing: 1px; }
.product-card .card-body { padding: 14px 16px; }
.product-card .product-name { font-weight: 600; font-size: .95rem; color: var(--text); margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.product-card .price { font-weight: 700; color: var(--primary); font-size: 1rem; }
.product-card .price-original { text-decoration: line-through; color: #aaa; font-size: .85rem; }
.product-card .color-dots { display: flex; gap: 5px; flex-wrap: wrap; margin: 6px 0; }
.color-dot { width: 18px; height: 18px; border-radius: 50%; border: 2px solid rgba(0,0,0,0.1); cursor: pointer; transition: transform .15s, border-color .15s; }
.color-dot.active, .color-dot:hover { transform: scale(1.25); border-color: var(--primary); }
.product-card .action-btns { display: flex; gap: 6px; }
.product-card .action-btns .btn { flex: 1; font-size: .82rem; padding: 6px 4px; }

/* Color/Size selectors (product detail) */
.size-btn { border: 2px solid #ddd; border-radius: 6px; padding: 5px 14px; cursor: pointer; font-size: .85rem; transition: all .15s; background: #fff; }
.size-btn:hover, .size-btn.active { border-color: var(--primary); background: var(--primary); color: #fff; }
.size-btn.oos { opacity: .4; cursor: not-allowed; text-decoration: line-through; }
.color-swatch { width: 32px; height: 32px; border-radius: 50%; border: 3px solid transparent; cursor: pointer; transition: border-color .15s, transform .15s; }
.color-swatch:hover, .color-swatch.active { border-color: var(--primary); transform: scale(1.15); }

/* Footer */
footer { background-color: var(--footer-bg); color: rgba(255,255,255,0.75); padding: 40px 0 20px; margin-top: 60px; }
footer h5 { color: #fff; font-weight: 700; margin-bottom: 16px; }
footer a { color: rgba(255,255,255,0.65); }
footer a:hover { color: var(--accent); }
.footer-bottom { border-top: 1px solid rgba(255,255,255,0.1); padding-top: 16px; margin-top: 30px; font-size: .82rem; text-align: center; }
.social-icons a { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(255,255,255,0.1); margin-right: 6px; transition: background .2s; font-size: 1rem; }
.social-icons a:hover { background: var(--accent); color: #fff; }

/* Carousel */
#heroCarousel .carousel-item { background: #1a1a1a; }
#heroCarousel .carousel-item img { height: 560px; width: 100%; object-fit: cover; object-position: center; }
#heroCarousel .carousel-caption { background: rgba(0,0,0,0.45); border-radius: 12px; padding: 20px 30px; }
#heroCarousel .carousel-indicators { margin-bottom: 1.25rem; }
#heroCarousel .carousel-control-prev, #heroCarousel .carousel-control-next { width: 6%; }

/* Cart Badge */
.cart-badge { position: absolute; top: -6px; right: -8px; background: var(--secondary); color: #fff; border-radius: 50%; width: 18px; height: 18px; font-size: .65rem; display: flex; align-items: center; justify-content: center; font-weight: 700; }

/* Section titles */
.section-title { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 700; color: var(--primary); position: relative; margin-bottom: 30px; }
.section-title::after { content: ''; display: block; width: 50px; height: 3px; background: var(--accent); margin-top: 8px; border-radius: 2px; }

/* WhatsApp Float Button */
.whatsapp-float { position: fixed; bottom: 24px; right: 24px; z-index: 1000; background: #25d366; color: #fff; width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; box-shadow: 0 4px 16px rgba(37,211,102,0.45); transition: transform .2s; }
.whatsapp-float:hover { transform: scale(1.1); color: #fff; }

/* Responsive tweaks */
@media (max-width: 576px) {
  #heroCarousel .carousel-item img { height: 280px; }
  .section-title { font-size: 1.3rem; }
  .product-card .card-img-wrap { height: 200px; }
  .navbar-brand { font-size: 1.2rem; }
}
@media (min-width: 577px) and (max-width: 991px) {
  #heroCarousel .carousel-item img { height: 400px; }
}
$extraCSS
</style>
</head>
<body>
HTML;
}

function renderNavbar() {
    $settings  = getSettings();
    $siteName  = $settings['site_name'] ?? 'Fashion Store';
    $logo      = $settings['site_logo'] ?? '';
    $waEnabled = $settings['whatsapp_orders_enabled'] ?? '1';
    $waNum     = preg_replace('/[^0-9]/', '', $settings['contact_whatsapp'] ?? '');

    $logoHtml = $logo
        ? '<img src="' . UPLOAD_URL . 'logos/' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($siteName) . '">'
        : '<span>' . htmlspecialchars($siteName) . '</span>';

    // Categories for nav
    $db   = getDB();
    $cats = $db->query("SELECT id,name,slug FROM categories WHERE parent_id IS NULL AND is_active=1 ORDER BY sort_order LIMIT 8")->fetchAll();
    $catItems = '';
    foreach ($cats as $c) {
        $catItems .= '<li><a class="dropdown-item" href="' . SITE_URL . '/shop.php?cat=' . urlencode($c['slug']) . '">' . htmlspecialchars($c['name']) . '</a></li>';
    }

    $siteUrl = SITE_URL;
    $customerNav = isCustomerLoggedIn()
        ? '<a class="nav-link" href="' . SITE_URL . '/customer/account.php"><i class="bi bi-person-circle"></i> My Account</a>'
        : '<a class="nav-link" href="' . SITE_URL . '/customer/login.php"><i class="bi bi-person"></i> Login</a>';

    echo <<<HTML
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
  <div class="container">
    <a class="navbar-brand" href="{$siteUrl}/index.php">$logoHtml</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="{$siteUrl}/index.php">Home</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Shop</a>
          <ul class="dropdown-menu">$catItems<li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="{$siteUrl}/shop.php">All Products</a></li></ul>
        </li>
        <li class="nav-item"><a class="nav-link" href="{$siteUrl}/offers.php"><i class="bi bi-tag-fill text-warning"></i> Offers</a></li>
      </ul>
      <form class="d-flex me-2" action="{$siteUrl}/shop.php" method="GET">
        <div class="input-group input-group-sm">
          <input class="form-control" type="search" name="q" placeholder="Search clothes..." style="min-width:180px;">
          <button class="btn btn-outline-light" type="submit"><i class="bi bi-search"></i></button>
        </div>
      </form>
      <ul class="navbar-nav gap-1">
        <li class="nav-item">{$customerNav}</li>
        <li class="nav-item"><a class="nav-link" href="{$siteUrl}/customer/account.php?tab=orders"><i class="bi bi-bag-check"></i> Orders</a></li>
        <li class="nav-item position-relative">
          <a class="nav-link" href="{$siteUrl}/cart.php"><i class="bi bi-cart3"></i> Cart <span class="cart-badge" id="cartCount">0</span></a>
        </li>
      </ul>
    </div>
  </div>
</nav>
HTML;
}

function renderFooter() {
    $s = getSettings();
    $fb = $s['facebook_url'] ?? '';
    $ig = $s['instagram_url'] ?? '';
    $tk = $s['tiktok_url'] ?? '';
    $wa = preg_replace('/[^0-9]/', '', $s['contact_whatsapp'] ?? '');
    $waLink = "https://wa.me/{$wa}";
    $year = date('Y');
    $name = htmlspecialchars($s['site_name'] ?? 'Fashion Store');
    $phone = htmlspecialchars($s['contact_phone'] ?? '');
    $email = htmlspecialchars($s['contact_email'] ?? '');
    $addr  = htmlspecialchars($s['contact_address'] ?? '');

    $socials = '';
    if ($fb)  $socials .= "<a href='" . htmlspecialchars($fb) . "' target='_blank'><i class='fab fa-facebook-f'></i></a>";
    if ($ig)  $socials .= "<a href='" . htmlspecialchars($ig) . "' target='_blank'><i class='fab fa-instagram'></i></a>";
    if ($tk)  $socials .= "<a href='" . htmlspecialchars($tk) . "' target='_blank'><i class='fab fa-tiktok'></i></a>";
    if ($wa)  $socials .= "<a href='{$waLink}' target='_blank'><i class='fab fa-whatsapp'></i></a>";

    echo <<<HTML
<footer>
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-4 col-md-6">
        <h5>$name</h5>
        <p class="small">{$s['site_tagline']}</p>
        <div class="social-icons mt-3">$socials</div>
      </div>
      <div class="col-lg-2 col-md-6">
        <h5>Quick Links</h5>
        <ul class="list-unstyled small">
          <li><a href="/shop/index.php">Home</a></li>
          <li><a href="/shop/shop.php">Shop</a></li>
          <li><a href="/shop/offers.php">Offers</a></li>
          <li><a href="/shop/customer/account.php?tab=orders">My Orders</a></li>
        </ul>
      </div>
      <div class="col-lg-3 col-md-6">
        <h5>Contact</h5>
        <ul class="list-unstyled small">
          <li><i class="bi bi-phone me-2"></i>$phone</li>
          <li><i class="bi bi-envelope me-2"></i>$email</li>
          <li><i class="bi bi-geo-alt me-2"></i>$addr</li>
          <li class="mt-2"><a href="$waLink" target="_blank" class="btn btn-sm" style="background:#25d366;color:#fff"><i class="fab fa-whatsapp me-1"></i> Chat on WhatsApp</a></li>
        </ul>
      </div>
      <div class="col-lg-3 col-md-6">
        <h5>We Accept</h5>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <span class="badge bg-secondary">Cash on Delivery</span>
          <span class="badge bg-secondary">Bank Transfer</span>
          <span class="badge" style="background:var(--btn-color)">PayHere</span>
          <span class="badge bg-dark">Koko</span>
        </div>
        <p class="small mt-3"><i class="bi bi-shield-check me-1"></i> Secure &amp; Safe Shopping</p>
      </div>
    </div>
    <div class="footer-bottom">
      &copy; $year $name. All rights reserved. | Designed with <i class="bi bi-heart-fill text-danger"></i> in Sri Lanka
    </div>
  </div>
</footer>
<a href="$waLink" target="_blank" class="whatsapp-float" title="Chat with us"><i class="fab fa-whatsapp"></i></a>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
toastr.options = { closeButton:true, progressBar:true, positionClass:'toast-top-right', timeOut:3500 };
window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
// Wrap fetch so all same-origin POST calls automatically carry the CSRF header.
(function(){
  const orig = window.fetch;
  window.fetch = function(input, init) {
    init = init || {};
    const method = (init.method || 'GET').toUpperCase();
    if (method !== 'GET' && method !== 'HEAD') {
      const url = typeof input === 'string' ? input : (input && input.url) || '';
      const sameOrigin = !/^https?:/i.test(url) || url.startsWith(location.origin);
      if (sameOrigin) {
        init.headers = new Headers(init.headers || {});
        if (!init.headers.has('X-CSRF-Token')) init.headers.set('X-CSRF-Token', window.CSRF_TOKEN);
      }
    }
    return orig(input, init);
  };
})();
function updateCartCount(){
  fetch('/shop/api/cart.php?action=count').then(r=>r.json()).then(d=>{ document.getElementById('cartCount').textContent = d.count||0; });
}
updateCartCount();
</script>
HTML;
}
