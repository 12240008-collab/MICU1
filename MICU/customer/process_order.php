<?php
session_start();
require_once '../config.php';
require_once '../helpers.php';

// Atur header response ke JSON untuk AJAX/Error handling
header('Content-Type: application/json');

// --- 1. Cek Metode dan Sesi Pengguna ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode permintaan tidak valid.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Silakan login kembali.']);
    exit;
}

$customer_id = $_SESSION['user_id'];

// --- 2. Ambil dan Validasi Input ---
$input = $_POST;

$partner_id          = isset($input['partner_id']) ? intval($input['partner_id']) : 0;
$service_id          = isset($input['service_id']) ? intval($input['service_id']) : 0;

// Berat estimasi TIDAK diperlukan, set 0.00 untuk database
$estimated_weight    = 0.00; 

$pickup_time_str     = isset($input['pickup_time']) ? trim($input['pickup_time']) : null;

// PERBAIKAN: Mengambil input dengan nama 'pickup_address' sesuai file order.php
$pickup_address      = isset($input['pickup_address']) ? trim($input['pickup_address']) : ''; 

$notes               = isset($input['notes']) ? trim($input['notes']) : null;

// Data Promo
$promo_code          = isset($input['promo_code']) && !empty($input['promo_code']) ? trim($input['promo_code']) : null;
$discount_type       = isset($input['discount_type']) && !empty($input['discount_type']) ? trim($input['discount_type']) : null;
$discount_value      = isset($input['discount_value']) ? floatval($input['discount_value']) : null;
$discount_applied    = isset($input['discount_applied']) ? floatval($input['discount_applied']) : 0.00;
// Mengambil flag has_pickup_delivery dari form untuk kalkulasi ongkir
$hasPickupDelivery   = (isset($input['use_pickup']) && $input['use_pickup'] === '1'); 

// VALIDASI BARU: Hanya perlu partner_id, service_id, dan alamat penjemputan
if (!$partner_id || !$service_id || empty($pickup_address)) {
    http_response_code(400);
    // Tambahkan detail jika Anda ingin debug lebih lanjut
    echo json_encode(['success' => false, 'message' => 'Data pesanan tidak lengkap (Mitra/Layanan/Alamat belum dipilih).']);
    exit;
}

// Format waktu penjemputan
$pickup_time = $pickup_time_str ? date('Y-m-d H:i:s', strtotime($pickup_time_str)) : null;
if ($pickup_time === false) {
    $pickup_time = null;
}

// --- 3. Ambil Data Partner dan Service untuk Biaya Antar-Jemput ---
try {
    // Ambil data partner (untuk delivery_fee dan user_id partner)
    $stmt = $conn->prepare("
        SELECT lp.delivery_fee, u.id as partner_user_id, u.full_name as partner_full_name, lp.laundry_name, lp.has_pickup_delivery
        FROM laundry_partners lp
        INNER JOIN users u ON lp.user_id = u.id
        WHERE lp.id = ? AND lp.is_active = 1
    ");
    $stmt->bind_param("i", $partner_id);
    $stmt->execute();
    $partnerResult = $stmt->get_result();
    $partnerData = $partnerResult->fetch_assoc();
    $stmt->close();

    if (!$partnerData) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Mitra Laundry tidak ditemukan atau tidak aktif.']);
        exit;
    }

    // Ambil harga layanan (Hanya untuk referensi jenis layanan, bukan untuk kalkulasi awal)
    $stmt = $conn->prepare("
        SELECT price_per_kg, service_name
        FROM services
        WHERE id = ? AND partner_id = ? AND is_active = 1
    ");
    $stmt->bind_param("ii", $service_id, $partner_id);
    $stmt->execute();
    $serviceResult = $stmt->get_result();
    $serviceData = $serviceResult->fetch_assoc();
    $stmt->close();

    if (!$serviceData) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Layanan yang dipilih tidak tersedia.']);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat mengambil data: ' . $e->getMessage()]);
    exit;
}


// --- 4. Perhitungan Biaya Awal (Hanya Ongkir dan Diskon) ---
$delivery_fee   = $partnerData['delivery_fee'];

// Jika Mitra tidak menawarkan atau Customer mematikan toggle (use_pickup), fee = 0
if (!$partnerData['has_pickup_delivery'] || !$hasPickupDelivery) {
    $delivery_fee = 0.00;
}

// Subtotal layanan (berat * harga) adalah 0 karena berat belum diketahui
$subtotal       = 0.00; 
$total_amount   = $subtotal + $delivery_fee - $discount_applied;

// Total Amount tidak boleh negatif
if ($total_amount < 0) {
    $total_amount = 0.00;
}

// --- 5. Generate Order Number ---
$order_number = generateOrderNumber($conn); // Asumsi fungsi ini ada di helpers.php

// --- 6. Memulai Transaksi Database ---
$conn->begin_transaction();
$success = true;

try {
    // --- 7. Insert ke Tabel orders ---
    $stmt = $conn->prepare("
        INSERT INTO orders (
            order_number,
            customer_id,
            partner_id,
            service_id,
            pickup_address,
            promo_code,
            discount_type,
            discount_value, 
            discount_applied,
            estimated_weight,
            notes,
            pickup_time,
            status,
            payment_status,
            subtotal,
            delivery_fee,
            total_amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid', ?, ?, ?)
    ");

    $stmt->bind_param("siiisssdssssddd",
        $order_number,
        $customer_id,
        $partner_id,
        $service_id,
        $pickup_address,
        $promo_code,
        $discount_type,
        $discount_value,
        $discount_applied,
        $estimated_weight, // Nilai 0.00
        $notes,
        $pickup_time,
        $subtotal,       // Nilai 0.00
        $delivery_fee,
        $total_amount    // Hanya berisi delivery_fee - discount_applied
    );
    $stmt->execute();
    $order_id = $conn->insert_id;
    $stmt->close();

    if (!$order_id) {
        throw new Exception("Gagal mendapatkan ID pesanan baru.");
    }

    // --- 8. Insert ke Tabel order_tracking ---
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (order_id, status, notes)
        VALUES (?, ?, ?)
    ");
    $initial_status = 'pending';
    $tracking_notes = 'Pesanan dibuat dan menunggu konfirmasi mitra untuk penjemputan.';
    $stmt->bind_param("iss", $order_id, $initial_status, $tracking_notes);
    $stmt->execute();
    $stmt->close();

    // --- 9. Insert Notifikasi ---
    $customer_name = $_SESSION['user_full_name'] ?? 'Pelanggan';

    // Notifikasi untuk Customer
    createNotification(
        $customer_id,
        'Pesanan Berhasil Dibuat',
        'Pesanan Anda (' . $order_number . ') telah dibuat. Kami akan menghubungi mitra untuk penjemputan.',
        'order',
        'tracking.php?order_id=' . $order_id
    );

    // Notifikasi untuk Partner (menggunakan partner_user_id)
    createNotification(
        $partnerData['partner_user_id'],
        'PESANAN BARU! Waktunya Penjemputan',
        'Pesanan baru dari ' . $customer_name . ' (' . $order_number . '). Harap segera konfirmasi pesanan dan atur penjemputan.',
        'order',
        '../partner/orders.php?order_id=' . $order_id
    );

    // --- 10. Commit Transaksi ---
    $conn->commit();

} catch (Exception $e) {
    // Jika ada error, rollback
    $conn->rollback();
    $success = false;

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Pemesanan gagal diproses: ' . $e->getMessage()]);
    exit;
}

// Di bagian response sukses (bagian akhir file process_order.php)
if ($success) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Pesanan berhasil dibuat!',
        'order_id' => $order_id,
        'redirect' => 'order_success.php?order_id=' . $order_id // Update redirect ke halaman sukses baru
    ]);
}

header('Location: order_success.php?order_id=' . $order_id);
exit;
?>