<?php
require_once __DIR__ . '/../includes/config.php';
start_secure_session('customer');
require_once __DIR__ . '/../includes/theme.php';

if (isCustomerLoggedIn()) { header('Location: /shop/customer/account.php'); exit; }

$db = getDB();
$tab    = $_GET['tab'] ?? 'login';
$error  = '';
$success = '';
// Only accept site-relative redirects to prevent open-redirect via ?redirect=https://attacker.example
$rawRedirect = $_GET['redirect'] ?? '/shop/customer/account.php';
$redirect = (is_string($rawRedirect) && preg_match('#^/[A-Za-z0-9_./?&=%-]+$#', $rawRedirect))
    ? $rawRedirect
    : '/shop/customer/account.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';

    if ($act === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $lockSeconds = login_lockout_remaining($email);
        if ($lockSeconds > 0) {
            $minutes = ceil($lockSeconds / 60);
            $error = "Too many failed attempts. Try again in {$minutes} minute(s).";
            $tab   = 'login';
        } else {
            $stmt = $db->prepare("SELECT * FROM customers WHERE email=? AND is_active=1");
            $stmt->execute([$email]);
            $customer = $stmt->fetch();
            if ($customer && password_verify($password, $customer['password_hash'])) {
                record_login_attempt($email, true);
                session_regenerate_id(true);
                $_SESSION['customer_id']    = $customer['id'];
                $_SESSION['customer_name']  = $customer['first_name'] . ' ' . $customer['last_name'];
                $_SESSION['customer_email'] = $customer['email'];
                $db->prepare("UPDATE customers SET last_login=NOW() WHERE id=?")->execute([$customer['id']]);
                header('Location: ' . $redirect); exit;
            }
            record_login_attempt($email, false);
            $error = 'Invalid email or password.';
            $tab   = 'login';
        }
    }

    if ($act === 'register') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';
        $tab       = 'register';

        if (!$firstName || !$lastName || !$email || !$password) { $error = 'All fields are required.'; }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'Invalid email address.'; }
        elseif (strlen($password) < 8) { $error = 'Password must be at least 8 characters.'; }
        elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $error = 'Password must include at least one letter and one number.';
        }
        elseif ($password !== $confirm) { $error = 'Passwords do not match.'; }
        else {
            $check = $db->prepare("SELECT id FROM customers WHERE email=?"); $check->execute([$email]);
            if ($check->fetch()) { $error = 'This email is already registered. Please login.'; }
            else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("INSERT INTO customers (first_name,last_name,email,phone,whatsapp,password_hash) VALUES (?,?,?,?,?,?)")
                   ->execute([$firstName,$lastName,$email,$phone,$phone,$hash]);
                $custId = $db->lastInsertId();
                session_regenerate_id(true);
                $_SESSION['customer_id']    = $custId;
                $_SESSION['customer_name']  = "$firstName $lastName";
                $_SESSION['customer_email'] = $email;
                header('Location: ' . $redirect); exit;
            }
        }
    }
}

renderHead('Login / Register');
renderNavbar();
?>

<div class="container my-5">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="card border-0 shadow" style="border-radius:16px;overflow:hidden">
        <div style="height:6px;background:linear-gradient(90deg,var(--primary),var(--secondary))"></div>
        <div class="card-body p-4">
          <h3 class="fw-bold text-center mb-4" style="font-family:'Playfair Display',serif">My Account</h3>

          <ul class="nav nav-pills nav-justified mb-4 gap-2">
            <li class="nav-item"><a class="nav-link fw-semibold <?= $tab==='login'?'active':'' ?>" href="?tab=login&redirect=<?= urlencode($redirect) ?>">Sign In</a></li>
            <li class="nav-item"><a class="nav-link fw-semibold <?= $tab==='register'?'active':'' ?>" href="?tab=register&redirect=<?= urlencode($redirect) ?>">Create Account</a></li>
          </ul>

          <?php if ($error): ?>
          <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if ($success): ?>
          <div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
          <?php endif; ?>

          <?php if ($tab === 'login'): ?>
          <!-- LOGIN FORM -->
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <div class="mb-3">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="email" class="form-control" placeholder="you@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="mb-4">
              <label class="form-label fw-semibold">Password</label>
              <div class="input-group">
                <input type="password" name="password" id="loginPwd" class="form-control" placeholder="••••••••" required>
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('loginPwd','eye1')"><i class="bi bi-eye" id="eye1"></i></button>
              </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</button>
          </form>
          <p class="text-center mt-3 mb-0 small text-muted">Don't have an account? <a href="?tab=register&redirect=<?= urlencode($redirect) ?>">Register free</a></p>

          <?php else: ?>
          <!-- REGISTER FORM -->
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="register">
            <div class="row g-3">
              <div class="col-6">
                <label class="form-label fw-semibold">First Name *</label>
                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">Last Name *</label>
                <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Email Address *</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Phone / WhatsApp *</label>
                <input type="tel" name="phone" class="form-control" placeholder="+94771234567" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Password *</label>
                <div class="input-group">
                  <input type="password" name="password" id="regPwd" class="form-control" placeholder="At least 8 chars, include a letter and a number" required minlength="8">
                  <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('regPwd','eye2')"><i class="bi bi-eye" id="eye2"></i></button>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Confirm Password *</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-person-plus me-2"></i>Create Account</button>
              </div>
            </div>
          </form>
          <p class="text-center mt-3 mb-0 small text-muted">Already have an account? <a href="?tab=login&redirect=<?= urlencode($redirect) ?>">Sign In</a></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function togglePwd(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    if (inp.type === 'password') { inp.type = 'text'; ico.className = 'bi bi-eye-slash'; }
    else { inp.type = 'password'; ico.className = 'bi bi-eye'; }
}
</script>
<?php renderFooter(); ?>
