<?php
// api/apply_promo.php
require_once __DIR__ . '/../includes/config.php';
start_secure_session('customer');
header('Content-Type: application/json');

$db       = getDB();
$code     = strtoupper(trim($_GET['code'] ?? ''));
$subtotal = floatval($_GET['subtotal'] ?? 0);

if (!$code) { echo json_encode(['error'=>'No code provided']); exit; }

$stmt = $db->prepare("SELECT * FROM offers WHERE offer_code=? AND is_active=1
    AND (start_date IS NULL OR start_date<=CURDATE())
    AND (end_date IS NULL OR end_date>=CURDATE())");
$stmt->execute([$code]);
$offer = $stmt->fetch();

if (!$offer) { echo json_encode(['error'=>'Invalid or expired promo code.']); exit; }
if ($subtotal < $offer['min_order_amount']) {
    echo json_encode(['error'=>'Min order Rs. '.number_format($offer['min_order_amount']).' required.']); exit;
}
if ($offer['usage_limit'] && $offer['used_count'] >= $offer['usage_limit']) {
    echo json_encode(['error'=>'Promo code usage limit reached.']); exit;
}

$discount = 0;
if ($offer['discount_type'] === 'percent') {
    $discount = $subtotal * ($offer['discount_value'] / 100);
    if ($offer['max_discount_amount']) $discount = min($discount, $offer['max_discount_amount']);
} else {
    $discount = min($offer['discount_value'], $subtotal);
}

echo json_encode(['success'=>true,'discount'=>round($discount,2),'message'=>"Code applied! You save Rs. ".number_format($discount,2)]);
