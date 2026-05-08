<?php
// ============================================================
// DATABASE CONFIGURATION
// Edit these values to match your hosting environment
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'clothing_store');
define('DB_USER', 'root');          // Change to your DB username
define('DB_PASS', '');              // Change to your DB password
define('DB_CHARSET', 'utf8mb4');

// Site base URL (no trailing slash)
define('SITE_URL', 'http://localhost/shop');
define('ADMIN_URL', SITE_URL . '/admin');

// Set APP_ENV to 'production' on a live server. Controls error display.
if (!defined('APP_ENV')) define('APP_ENV', 'development');

// Upload paths
define('UPLOAD_PATH', __DIR__ . '/../assets/images/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/images/uploads/');
define('PRODUCT_UPLOAD_PATH', UPLOAD_PATH . 'products/');
define('BANNER_UPLOAD_PATH', UPLOAD_PATH . 'banners/');
define('LOGO_UPLOAD_PATH', UPLOAD_PATH . 'logos/');
define('SLIP_UPLOAD_PATH', UPLOAD_PATH . 'slips/');

// Session config
define('SESSION_NAME_CUSTOMER', 'clothing_store_session');
define('SESSION_NAME_ADMIN', 'clothing_store_admin_session');
// Backwards-compat names used in older parts of the code.
define('SESSION_NAME', SESSION_NAME_CUSTOMER);
define('ADMIN_SESSION_NAME', SESSION_NAME_ADMIN);

// Login rate-limit policy (admin)
define('LOGIN_MAX_FAILS', 5);
define('LOGIN_LOCKOUT_SECONDS', 900); // 15 minutes

// ============================================================
// ERROR REPORTING
// In production: never expose errors to the browser.
// ============================================================
if (APP_ENV === 'production') {
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    @ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
@ini_set('log_errors', '1');

// ============================================================
// SECURE SESSION
// Call start_secure_session('admin'|'customer') before any
// session access. Sets secure cookie flags and starts.
// ============================================================
function start_secure_session($scope = 'customer') {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $name = $scope === 'admin' ? SESSION_NAME_ADMIN : SESSION_NAME_CUSTOMER;
    session_name($name);

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['SERVER_PORT'] ?? '') === '443')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $params = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($params);
    } else {
        session_set_cookie_params($params['lifetime'], $params['path'] . '; samesite=' . $params['samesite'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_start();

    // Initialise CSRF token once per session.
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}

// ============================================================
// CSRF HELPERS
// ============================================================
function csrf_token() {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// Returns true if the request token matches. Looks at POST, headers, and JSON body.
function csrf_verify() {
    $expected = $_SESSION['_csrf'] ?? '';
    if (!$expected) return false;

    $token = $_POST['_csrf'] ?? '';
    if (!$token) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (!$token && (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false)) {
        $raw = file_get_contents('php://input');
        $json = $raw ? json_decode($raw, true) : null;
        if (is_array($json) && isset($json['_csrf'])) $token = $json['_csrf'];
    }
    return is_string($token) && hash_equals($expected, $token);
}

// Hard-fails the request if CSRF check fails. Use on every state-changing endpoint.
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!csrf_verify()) {
        http_response_code(403);
        if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid or missing CSRF token. Please refresh the page and try again.']);
        } else {
            echo '<!doctype html><meta charset="utf-8"><title>403</title><h1>403 — Invalid request</h1><p>Your form session expired. Please go back, refresh the page, and try again.</p>';
        }
        exit;
    }
}

// ============================================================
// DATABASE CONNECTION (PDO)
// ============================================================
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[DB connect] ' . $e->getMessage());
            http_response_code(500);
            $msg = APP_ENV === 'production'
                ? 'A database error occurred. Please try again later.'
                : 'Database connection failed: ' . $e->getMessage();
            if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                header('Content-Type: application/json');
                echo json_encode(['error' => $msg]);
            } else {
                echo '<!doctype html><meta charset="utf-8"><title>500</title><h1>500 — Service unavailable</h1><p>' . htmlspecialchars($msg) . '</p>';
            }
            exit;
        }
    }
    return $pdo;
}

// ============================================================
// LOGIN ATTEMPTS (rate limit)
// ============================================================
function ensure_login_attempts_table() {
    static $done = false;
    if ($done) return;
    try {
        getDB()->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            username VARCHAR(150) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_time (ip, attempted_at),
            INDEX idx_user_time (username, attempted_at)
        )");
    } catch (Exception $e) {
        error_log('[ensure_login_attempts_table] ' . $e->getMessage());
    }
    $done = true;
}

function client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Returns 0 if not locked, otherwise number of seconds remaining.
function login_lockout_remaining($username) {
    ensure_login_attempts_table();
    $db  = getDB();
    $ip  = client_ip();
    $stmt = $db->prepare("SELECT COUNT(*) AS fails, MAX(attempted_at) AS last_at
        FROM login_attempts
        WHERE success=0
          AND attempted_at >= (NOW() - INTERVAL ? SECOND)
          AND (ip=? OR username=?)");
    $stmt->execute([LOGIN_LOCKOUT_SECONDS, $ip, $username]);
    $row = $stmt->fetch();
    if (!$row || $row['fails'] < LOGIN_MAX_FAILS) return 0;
    $lastTs   = strtotime($row['last_at']);
    $unlockTs = $lastTs + LOGIN_LOCKOUT_SECONDS;
    return max(0, $unlockTs - time());
}

function record_login_attempt($username, $success) {
    ensure_login_attempts_table();
    try {
        getDB()->prepare("INSERT INTO login_attempts (ip, username, success) VALUES (?,?,?)")
               ->execute([client_ip(), substr($username, 0, 150), $success ? 1 : 0]);
        if ($success) {
            getDB()->prepare("DELETE FROM login_attempts WHERE username=? AND success=0")->execute([$username]);
        }
    } catch (Exception $e) {
        error_log('[record_login_attempt] ' . $e->getMessage());
    }
}

// ============================================================
// SITE SETTINGS LOADER
// ============================================================
function getSettings() {
    static $settings = null;
    if ($settings === null) {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log('[getSettings] ' . $e->getMessage());
            $settings = [];
        }
    }
    return $settings;
}

function getSetting($key, $default = '') {
    $settings = getSettings();
    return isset($settings[$key]) ? $settings[$key] : $default;
}

// ============================================================
// ACTIVE THEME LOADER
// ============================================================
function getActiveTheme() {
    static $theme = null;
    if ($theme === null) {
        $fallback = ['primary_color'=>'#2c3e50','secondary_color'=>'#e74c3c','accent_color'=>'#f39c12','bg_color'=>'#f8f9fa','text_color'=>'#333333','navbar_color'=>'#2c3e50','footer_color'=>'#1a252f','button_color'=>'#e74c3c','badge_color'=>'#f39c12'];
        try {
            $db = getDB();
            $stmt = $db->query("SELECT * FROM color_themes WHERE is_active = 1 LIMIT 1");
            $theme = $stmt->fetch() ?: $fallback;
        } catch (Exception $e) {
            error_log('[getActiveTheme] ' . $e->getMessage());
            $theme = $fallback;
        }
    }
    return $theme;
}

// ============================================================
// HELPERS
// ============================================================
function generateOrderNumber() {
    return 'ORD-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)) . '-' . date('Ymd');
}

function formatPrice($amount) {
    $currency = getSetting('currency_symbol', 'Rs.');
    return $currency . ' ' . number_format($amount, 2);
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function slugify($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

// Validate by reading the actual file bytes via finfo, not the client-supplied MIME.
function uploadImage($file, $destination, $prefix = 'img') {
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $allowedExt  = ['jpg','jpeg','png','webp','gif'];

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'Invalid upload'];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['error' => 'Upload failed (code ' . $file['error'] . ')'];
    }
    if ($file['size'] > 5 * 1024 * 1024) return ['error' => 'File too large (max 5MB)'];

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $realMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
    if ($finfo) finfo_close($finfo);
    if (!in_array($realMime, $allowedMime, true)) {
        return ['error' => 'Invalid file type'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        // Map MIME → safe extension when the original is suspicious.
        $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$realMime] ?? 'jpg';
    }

    if (!is_dir($destination)) mkdir($destination, 0755, true);

    $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $path = $destination . $filename;

    if (move_uploaded_file($file['tmp_name'], $path)) {
        return ['success' => true, 'filename' => $filename];
    }
    return ['error' => 'Upload failed'];
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function isCustomerLoggedIn() {
    return isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']);
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . ADMIN_URL . '/login.php');
        exit;
    }
}

function requireCustomer() {
    if (!isCustomerLoggedIn()) {
        header('Location: ' . SITE_URL . '/customer/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

// WhatsApp message builder
function buildWhatsAppOrderMessage($product, $color, $size, $productCode) {
    $waNumber = getSetting('contact_whatsapp', '+94771234567');
    $waNumber = preg_replace('/[^0-9]/', '', $waNumber);
    $message = "Hello! I want to order:\n\n";
    $message .= "🛍 *Item:* {$product}\n";
    $message .= "📦 *Code:* {$productCode}\n";
    $message .= "🎨 *Color:* {$color}\n";
    $message .= "📏 *Size:* {$size}\n\n";
    $message .= "Please confirm availability and payment details.";
    return "https://wa.me/{$waNumber}?text=" . urlencode($message);
}

// Ensure upload dirs exist
$dirs = [PRODUCT_UPLOAD_PATH, BANNER_UPLOAD_PATH, LOGO_UPLOAD_PATH];
if (!defined('SLIP_UPLOAD_PATH')) define('SLIP_UPLOAD_PATH', UPLOAD_PATH . 'slips/');
$dirs[] = SLIP_UPLOAD_PATH;
foreach ($dirs as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}
