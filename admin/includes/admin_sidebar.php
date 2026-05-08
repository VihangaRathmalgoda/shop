<?php
// admin/includes/admin_sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));
function isActive($pages) {
    global $currentPage;
    return in_array($currentPage, (array)$pages) ? 'active' : '';
}
?>
<div id="adminSidebar">
  <div class="sidebar-brand">
    <i class="bi bi-shop fs-4" style="color:var(--accent)"></i>
    <span>FashionAdmin</span>
  </div>
  <nav class="nav flex-column mt-2 pb-4">
    <span class="nav-section">Main</span>
    <a class="nav-link <?= isActive('index.php') ?>" href="/shop/admin/index.php">
      <i class="bi bi-speedometer2"></i><span>Dashboard</span>
    </a>

    <span class="nav-section">Orders</span>
    <a class="nav-link <?= isActive('orders.php') ?>" href="/shop/admin/orders.php">
      <i class="bi bi-bag-check"></i><span>All Orders</span>
    </a>
    <a class="nav-link <?= isActive('order_detail.php') ?>" href="/shop/admin/orders.php?status=pending">
      <i class="bi bi-clock"></i><span>Pending Orders</span>
    </a>

    <span class="nav-section">Products</span>
    <a class="nav-link <?= isActive('products.php') ?>" href="/shop/admin/products.php">
      <i class="bi bi-box-seam"></i><span>Products</span>
    </a>
    <a class="nav-link <?= isActive('categories.php') ?>" href="/shop/admin/categories.php">
      <i class="bi bi-grid"></i><span>Categories</span>
    </a>
    <a class="nav-link <?= isActive('stock.php') ?>" href="/shop/admin/stock.php">
      <i class="bi bi-clipboard-data"></i><span>Stock Manager</span>
    </a>

    <span class="nav-section">Marketing</span>
    <a class="nav-link <?= isActive('offers.php') ?>" href="/shop/admin/offers.php">
      <i class="bi bi-tag"></i><span>Offers & Discounts</span>
    </a>
    <a class="nav-link <?= isActive('banners.php') ?>" href="/shop/admin/banners.php">
      <i class="bi bi-image"></i><span>Banners</span>
    </a>

    <span class="nav-section">Customers</span>
    <a class="nav-link <?= isActive('customers.php') ?>" href="/shop/admin/customers.php">
      <i class="bi bi-people"></i><span>Customers</span>
    </a>

    <span class="nav-section">Settings</span>
    <a class="nav-link <?= isActive('settings.php') ?>" href="/shop/admin/settings.php">
      <i class="bi bi-gear"></i><span>General Settings</span>
    </a>
    <a class="nav-link" href="/shop/admin/settings.php?tab=themes">
      <i class="bi bi-palette"></i><span>Color Themes</span>
    </a>
  </nav>
</div>
