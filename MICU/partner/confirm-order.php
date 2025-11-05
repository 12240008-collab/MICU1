<?php
session_start();
require_once '../config.php';
requirePartner();

$db = db();

// Ambil partner
$stmt = $db->prepare("SELECT id FROM laundry_partners WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$partner = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$partner) {
    $_SESSION['flash_error'] = 'Partner tidak ditemukan.';
    redirect('partner/dashboard.php'); // UBAH DI SINI
}

$partner_id = $partner['id'];
$order_id = (int)($_GET['id'] ?? 0);

if (!$order_id) {
    $_SESSION['flash_error'] = 'ID order tidak valid.';
    redirect('partner/dashboard.php'); // UBAH DI SINI
}

$stmt = $db->prepare("
    SELECT o.*, s.service_name, s.price_per_kg, lp.laundry_name, lp.delivery_fee, u.full_name as customer_name
    FROM orders o
    JOIN services s ON o.service_id = s.id
    JOIN laundry_partners lp ON o.partner_id = lp.id
    JOIN users u ON o.customer_id = u.id
    WHERE o.id = ? AND o.partner_id = ? AND o.status = 'pending'
");
$stmt->execute([$order_id, $partner_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $debug_stmt = $db->prepare("SELECT status, partner_id FROM orders WHERE id = ?");
    $debug_stmt->execute([$order_id]);
    $debug = $debug_stmt->fetch(PDO::FETCH_ASSOC);

    $msg = "Order tidak ditemukan atau bukan status pending.";
    if ($debug) {
        $msg .= "<br>Status: <strong>{$debug['status']}</strong>";
        $msg .= "<br>Partner ID: <strong>{$debug['partner_id']}</strong> (Anda: $partner_id)";
    }
    $_SESSION['flash_error'] = $msg;
    redirect('partner/dashboard.php'); // UBAH DI SINI
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual_weight = (float)($_POST['actual_weight'] ?? 0);
    
    if ($actual_weight <= 0) {
        $error = 'Berat harus lebih dari 0 kg.';
    } else {
        $subtotal = $actual_weight * $order['price_per_kg'];
        $total_amount = $subtotal + $order['delivery_fee'];

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("UPDATE orders SET actual_weight = ?, subtotal = ?, total_amount = ?, status = 'waiting_payment' WHERE id = ?");
            $stmt->execute([$actual_weight, $subtotal, $total_amount, $order_id]);

            $stmt = $db->prepare("INSERT INTO order_tracking (order_id, status, notes) VALUES (?, 'waiting_payment', ?)");
            $stmt->execute([$order_id, "Berat aktual: {$actual_weight} kg"]);

            $db->commit();

            $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'order', ?)");
            $stmt->execute([
                $order['customer_id'],
                'Berat Dikonfirmasi!',
                "Berat: {$actual_weight} kg\nTotal: Rp " . number_format($total_amount, 0, ',', '.') . "\nSilakan bayar.",
                '../customer/payment.php?order_id=' . $order_id
            ]);

            $_SESSION['flash_success'] = 'Berat dikonfirmasi! Menunggu pembayaran.';
            redirect('partner/dashboard.php'); // UBAH DI SINI
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Gagal: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Berat</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-6">
    <div class="max-w-md mx-auto bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center mb-6">
            <!-- TOMBOL KEMBALI -->
            <a href="partner/dashboard.php" class="mr-3">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-gray-800">Konfirmasi Berat</h1>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= $error ?></div>
        <?php endif; ?>

        <div class="space-y-2 text-sm mb-6 bg-gray-50 p-4 rounded-lg">
            <p><strong>No. Pesanan:</strong> <?= htmlspecialchars($order['order_number']) ?></p>
            <p><strong>Pelanggan:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
            <p><strong>Layanan:</strong> <?= htmlspecialchars($order['service_name']) ?></p>
            <p><strong>Estimasi:</strong> <?= $order['estimated_weight'] ?> kg</p>
            <p><strong>Harga/kg:</strong> <?= formatRupiah($order['price_per_kg']) ?></p>
        </div>

        <form method="POST">
            <label class="block text-gray-700 font-medium mb-2">Berat Aktual (kg) *</label>
            <input type="number" name="actual_weight" step="0.1" min="0.1" required 
                   class="w-full p-3 border rounded-lg mb-4 focus:ring-2 focus:ring-blue-500" 
                   placeholder="Contoh: 5.5" value="<?= $_POST['actual_weight'] ?? '' ?>">

            <div class="flex space-x-2">
                <!-- TOMBOL BATAL -->
                <a href="dashboard.php" class="flex-1 text-center bg-gray-300 hover:bg-gray-400 py-2 rounded-lg font-medium transition">
                    Batal
                </a>
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-medium transition">
                    Konfirmasi & Minta Bayar
                </button>
            </div>
        </form>
    </div>
</body>
</html>