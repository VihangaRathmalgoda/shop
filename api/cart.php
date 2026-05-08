<?php
// api/cart.php - Cart management API
require_once __DIR__ . '/../includes/config.php';
start_secure_session('customer');

header('Content-Type: application/json');

// All write operations require a valid CSRF token. Read-only "count" / "get" via GET are exempt.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    csrf_check();
}

$db     = getDB();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Use customer_id if logged in, else session_id
$customerId = $_SESSION['customer_id'] ?? null;
$sessionId  = $customerId ? null : session_id();

function getCartItems($db, $customerId, $sessionId) {
    if ($customerId) {
        $stmt = $db->prepare("SELECT c.*, p.name, p.base_price, p.sale_price, p.is_on_sale, p.status,
            pc.color_name, pc.color_hex, ps.size_label,
            (SELECT image_path FROM product_images WHERE product_id=c.product_id AND color_id=c.color_id LIMIT 1) as img,
            (SELECT quantity FROM product_stock WHERE product_id=c.product_id AND color_id=c.color_id AND size_id=c.size_id) as stock_qty
            FROM cart c JOIN products p ON c.product_id=p.id LEFT JOIN product_colors pc ON c.color_id=pc.id LEFT JOIN product_sizes ps ON c.size_id=ps.id
            WHERE c.customer_id=?");
        $stmt->execute([$customerId]);
    } else {
        $stmt = $db->prepare("SELECT c.*, p.name, p.base_price, p.sale_price, p.is_on_sale, p.status,
            pc.color_name, pc.color_hex, ps.size_label,
            (SELECT image_path FROM product_images WHERE product_id=c.product_id AND color_id=c.color_id LIMIT 1) as img,
            (SELECT quantity FROM product_stock WHERE product_id=c.product_id AND color_id=c.color_id AND size_id=c.size_id) as stock_qty
            FROM cart c JOIN products p ON c.product_id=p.id LEFT JOIN product_colors pc ON c.color_id=pc.id LEFT JOIN product_sizes ps ON c.size_id=ps.id
            WHERE c.session_id=?");
        $stmt->execute([$sessionId]);
    }
    return $stmt->fetchAll();
}

if ($action === 'count') {
    if ($customerId) {
        $stmt = $db->prepare("SELECT SUM(quantity) FROM cart WHERE customer_id=?"); $stmt->execute([$customerId]);
    } else {
        $stmt = $db->prepare("SELECT SUM(quantity) FROM cart WHERE session_id=?"); $stmt->execute([$sessionId]);
    }
    echo json_encode(['count' => intval($stmt->fetchColumn())]);
    exit;
}

if ($action === 'get') {
    $items = getCartItems($db, $customerId, $sessionId);
    $total = 0;
    foreach ($items as &$item) {
        $price = ($item['is_on_sale'] && $item['sale_price']) ? $item['sale_price'] : $item['base_price'];
        $item['unit_price'] = $price;
        $item['line_total'] = $price * $item['quantity'];
        $item['img_url'] = $item['img'] ? UPLOAD_URL . 'products/' . $item['img'] : SITE_URL . '/assets/images/placeholder.png';
        $total += $item['line_total'];
    }
    echo json_encode(['items' => $items, 'subtotal' => $total]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$act2  = $input['action'] ?? $action;

if ($act2 === 'add') {
    $productId = intval($input['product_id'] ?? 0);
    $colorId   = intval($input['color_id'] ?? 0);
    $sizeId    = intval($input['size_id'] ?? 0);
    $qty       = max(1, intval($input['quantity'] ?? 1));

    // Stock check
    $stock = $db->prepare("SELECT quantity FROM product_stock WHERE product_id=? AND color_id=? AND size_id=?");
    $stock->execute([$productId, $colorId, $sizeId]);
    $stockQty = intval($stock->fetchColumn());
    if ($stockQty < $qty) { echo json_encode(['error' => 'Insufficient stock']); exit; }

    if ($customerId) {
        $check = $db->prepare("SELECT id,quantity FROM cart WHERE customer_id=? AND product_id=? AND color_id=? AND size_id=?");
        $check->execute([$customerId,$productId,$colorId,$sizeId]);
    } else {
        $check = $db->prepare("SELECT id,quantity FROM cart WHERE session_id=? AND product_id=? AND color_id=? AND size_id=?");
        $check->execute([$sessionId,$productId,$colorId,$sizeId]);
    }
    $existing = $check->fetch();

    if ($existing) {
        $newQty = min($existing['quantity'] + $qty, $stockQty);
        $db->prepare("UPDATE cart SET quantity=?, updated_at=NOW() WHERE id=?")->execute([$newQty, $existing['id']]);
    } else {
        if ($customerId) {
            $db->prepare("INSERT INTO cart (customer_id,product_id,color_id,size_id,quantity) VALUES (?,?,?,?,?)")->execute([$customerId,$productId,$colorId,$sizeId,$qty]);
        } else {
            $db->prepare("INSERT INTO cart (session_id,product_id,color_id,size_id,quantity) VALUES (?,?,?,?,?)")->execute([$sessionId,$productId,$colorId,$sizeId,$qty]);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($act2 === 'update') {
    $cartId = intval($input['cart_id'] ?? 0);
    $qty    = max(0, intval($input['quantity'] ?? 1));
    if ($qty === 0) {
        $db->prepare("DELETE FROM cart WHERE id=?")->execute([$cartId]);
    } else {
        $db->prepare("UPDATE cart SET quantity=? WHERE id=?")->execute([$qty, $cartId]);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($act2 === 'remove') {
    $cartId = intval($input['cart_id'] ?? 0);
    $db->prepare("DELETE FROM cart WHERE id=?")->execute([$cartId]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
