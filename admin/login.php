<?php
require_once __DIR__ . '/../includes/config.php';
start_secure_session('admin');

if (isAdminLoggedIn()) { header('Location: /shop/admin/index.php'); exit; }

$error = '';
$lockSeconds = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $lockSeconds = login_lockout_remaining($username);
    if ($lockSeconds > 0) {
        $minutes = ceil($lockSeconds / 60);
        $error = "Too many failed attempts. Try again in {$minutes} minute(s).";
    } elseif ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE (username=? OR email=?) AND is_active=1");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            record_login_attempt($username, true);
            // Mitigate session fixation: rotate the session ID after auth.
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role'];
            $db->prepare("UPDATE admin_users SET last_login=NOW() WHERE id=?")->execute([$admin['id']]);
            header('Location: /shop/admin/index.php'); exit;
        }
        record_login_attempt($username, false);
        $error = 'Invalid username or password.';
        $lockSeconds = login_lockout_remaining($username);
        if ($lockSeconds > 0) {
            $minutes = ceil($lockSeconds / 60);
            $error = "Too many failed attempts. Try again in {$minutes} minute(s).";
        }
    } else {
        $error = 'Please enter username and password.';
    }
}

$settings = [];
try { $settings = getSettings(); } catch(Exception $e) {}
$siteName = $settings['site_name'] ?? 'Fashion Store';
$theme    = [];
try { $theme = getActiveTheme(); } catch(Exception $e) {
    $theme = ['primary_color'=>'#2c3e50','secondary_color'=>'#e74c3c','accent_color'=>'#f39c12','navbar_color'=>'#2c3e50'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
body { font-family:'Poppins',sans-serif; background: linear-gradient(135deg, <?= $theme['primary_color'] ?> 0%, <?= $theme['secondary_color'] ?> 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; }
.login-card { background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.2); padding:40px; width:100%; max-width:420px; }
.login-brand { text-align:center; margin-bottom:30px; }
.login-brand .brand-icon { width:64px; height:64px; border-radius:16px; background:linear-gradient(135deg,<?= $theme['primary_color'] ?>,<?= $theme['secondary_color'] ?>); display:flex; align-items:center; justify-content:center; margin:0 auto 12px; }
.login-brand h4 { font-weight:700; color:#1e2a3a; margin-bottom:4px; }
.login-brand p { color:#6c757d; font-size:.88rem; }
.btn-login { background:linear-gradient(135deg,<?= $theme['primary_color'] ?>,<?= $theme['secondary_color'] ?>); color:#fff; border:none; padding:12px; font-weight:600; border-radius:8px; }
.btn-login:hover { filter:brightness(90%); color:#fff; }
.form-control { border-radius:8px; padding:10px 14px; font-size:.9rem; }
.form-control:focus { box-shadow:0 0 0 3px rgba(44,62,80,0.15); border-color:<?= $theme['primary_color'] ?>; }
.input-group-text { border-radius:8px 0 0 8px; background:#f8f9fa; }
</style>
</head>
<body>
<div class="login-card">
  <div class="login-brand">
    <div class="brand-icon"><i class="bi bi-shop text-white fs-3"></i></div>
    <h4><?= htmlspecialchars($siteName) ?></h4>
    <p>Admin Portal — Sign In</p>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" <?= $lockSeconds > 0 ? 'aria-disabled="true"' : '' ?>>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label fw-semibold">Username / Email</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-person"></i></span>
        <input type="text" name="username" class="form-control" placeholder="admin" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus <?= $lockSeconds > 0 ? 'disabled' : '' ?>>
      </div>
    </div>
    <div class="mb-4">
      <label class="form-label fw-semibold">Password</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input type="password" name="password" id="pwdInput" class="form-control" placeholder="••••••••" required <?= $lockSeconds > 0 ? 'disabled' : '' ?>>
        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()"><i class="bi bi-eye" id="eyeIcon"></i></button>
      </div>
    </div>
    <button type="submit" class="btn btn-login w-100 mb-3" <?= $lockSeconds > 0 ? 'disabled' : '' ?>><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</button>
    <p class="text-center text-muted small mb-0">Default: admin / Admin@1234</p>
  </form>
  <hr>
  <p class="text-center mb-0"><a href="/shop/index.php" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Back to Store</a></p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
    const inp = document.getElementById('pwdInput');
    const ico = document.getElementById('eyeIcon');
    if (inp.type === 'password') { inp.type = 'text'; ico.className = 'bi bi-eye-slash'; }
    else { inp.type = 'password'; ico.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
