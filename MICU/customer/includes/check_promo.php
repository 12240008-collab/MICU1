<?php
require_once '../../config.php';

header('Content-Type: application/json');

$partner_id = intval($_GET['partner_id'] ?? 0);
$code = trim($_GET['code'] ?? '');
$subtotal = floatval($_GET['subtotal'] ?? 0);

if (!$partner_id || !$code) {
    echo json_encode([
        'valid' => false, 
        'error' => 'Parameter tidak lengkap'
    ]);
    exit;
}

// Query untuk cek promo
$stmt = $conn->prepare("
    SELECT * FROM partner_promotions 
    WHERE partner_id = ? 
    AND code = ? 
    AND (expires_at IS NULL OR expires_at > NOW())
    AND (max_uses IS NULL OR used_count < max_uses)
");

$stmt->bind_param("is", $partner_id, $code);
$stmt->execute();
$result = $stmt->get_result();
$promo = $result->fetch_assoc();

if (!$promo) {
    echo json_encode([
        'valid' => false, 
        'error' => 'Kode promo tidak valid atau sudah kadaluarsa'
    ]);
    exit;
}

// Cek minimum order
if ($subtotal < $promo['min_order']) {
    echo json_encode([
        'valid' => false,
        'error' => 'Minimal pembelian Rp' . number_format($promo['min_order'],0,',','.')
    ]);
    exit;
}

echo json_encode([
    'valid' => true,
    'code' => $promo['code'],
    'discount_type' => $promo['discount_type'],
    'discount_value' => $promo['discount_value'],
    'min_order' => $promo['min_order']
]);