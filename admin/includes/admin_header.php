<?php
// admin/includes/admin_header.php
require_once __DIR__ . '/../../includes/config.php';
$theme = getActiveTheme();
$settings = getSettings();
$siteName = $settings['site_name'] ?? 'Fashion Store';
function adminThemeCSS($t) {
    return ":root{--primary:{$t['primary_color']};--secondary:{$t['secondary_color']};--accent:{$t['accent_color']};--navbar-bg:{$t['navbar_color']};--btn-color:{$t['button_color']};}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin | <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
<?= adminThemeCSS($theme) ?>
* { box-sizing: border-box; }
body { font-family: 'Poppins', sans-serif; background: #f4f6fb; min-height: 100vh; }

/* Admin sidebar */
#adminSidebar { width: 250px; min-height: 100vh; background: #1e2a3a; color: #cdd6e0; position: sticky; top: 0; flex-shrink: 0; transition: width .25s; overflow: hidden; }
#adminSidebar.collapsed { width: 64px; }
#adminSidebar .sidebar-brand { padding: 18px 16px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 10px; }
#adminSidebar .sidebar-brand span { font-weight: 700; font-size: 1rem; white-space: nowrap; color: #fff; }
#adminSidebar .nav-link { color: #9bb0c8; padding: 10px 16px; border-radius: 8px; margin: 2px 8px; display: flex; align-items: center; gap: 10px; white-space: nowrap; font-size: .88rem; transition: background .15s, color .15s; }
#adminSidebar .nav-link:hover, #adminSidebar .nav-link.active { background: rgba(255,255,255,0.1); color: #fff; }
#adminSidebar .nav-link i { font-size: 1.1rem; min-width: 20px; }
#adminSidebar .nav-section { font-size: .68rem; text-transform: uppercase; letter-spacing: 1px; color: #5a7085; padding: 14px 24px 4px; white-space: nowrap; }
#adminSidebar.collapsed .nav-section, #adminSidebar.collapsed .nav-link span { display: none; }

/* Admin topbar */
#adminTopbar { background: #fff; border-bottom: 1px solid #e8ecf0; padding: 10px 20px; display: flex; align-items: center; gap: 12px; position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 8px rgba(0,0,0,0.06); }

/* Content area */
#adminContent { min-height: 100vh; max-width: calc(100vw - 250px); }

/* Tables */
.table th { font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: #6c757d; }
.table td { font-size: .88rem; vertical-align: middle; }

/* Cards */
.card { border-radius: 10px; }
.card-header { font-size: .9rem; }

/* Form controls */
.form-label { font-weight: 500; font-size: .88rem; }
.form-control, .form-select { font-size: .9rem; }

/* Responsive */
@media (max-width: 768px) {
  #adminSidebar { width: 64px; }
  #adminSidebar .nav-link span, #adminSidebar .nav-section, #adminSidebar .sidebar-brand span { display: none; }
  #adminContent { max-width: 100vw; }
}

/* Color preview */
.color-preview { width: 28px; height: 28px; border-radius: 50%; border: 2px solid rgba(0,0,0,0.1); display: inline-block; vertical-align: middle; }

/* Image thumbs in tables */
.tbl-img { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; }

/* Status badges */
.status-pending { background: #fff3cd; color: #856404; }
.status-confirmed { background: #cfe2ff; color: #084298; }
.status-processing { background: #d1ecf1; color: #0c5460; }
.status-shipped { background: #d4edda; color: #155724; }
.status-delivered { background: #198754; color: #fff; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.status-returned { background: #e2e3e5; color: #383d41; }
</style>
</head>
<body>
<div id="adminTopbar">
  <button class="btn btn-sm btn-outline-secondary" onclick="toggleSidebar()"><i class="bi bi-list fs-5"></i></button>
  <span class="fw-bold me-auto" style="color:var(--primary)"><?= htmlspecialchars($siteName) ?> Admin</span>
  <a href="/shop/index.php" target="_blank" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-eye me-1"></i>View Site</a>
  <span class="small text-muted me-2"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
  <a href="/shop/admin/logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
</div>
<script>
function toggleSidebar(){
  document.getElementById('adminSidebar').classList.toggle('collapsed');
}
</script>
