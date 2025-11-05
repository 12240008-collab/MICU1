<?php
session_start();
require_once '../config.php';
requirePartner();

$user = getCurrentUser();
$db = db();

// Get partner info
$stmt = $db->prepare("SELECT * FROM laundry_partners WHERE user_id = ?");
$stmt->execute([$user['id']]);
$partner = $stmt->fetch();

if (!$partner) {
    redirect('partner/login.php');
}

$partner_id = $partner['id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get order details
$stmt = $db->prepare("
    SELECT o.*, 
           u.full_name as customer_name, u.phone as customer_phone, u.email as customer_email,
           s.service_name, s.price_per_kg,
           p.status as payment_status, p.payment_method
    FROM orders o
    JOIN users u ON o.customer_id = u.id
    JOIN services s ON o.service_id = s.id
    LEFT JOIN payments p ON o.id = p.order_id
    WHERE o.id = ? AND o.partner_id = ?
");
$stmt->execute([$order_id, $partner_id]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error'] = 'Pesanan tidak ditemukan';
    redirect('partner/dashboard.php');
}

// Get tracking history
$stmt = $db->prepare("
    SELECT * FROM order_tracking 
    WHERE order_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$order_id]);
$tracking_history = $stmt->fetchAll();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'accept_order':
                $stmt = $db->prepare("UPDATE orders SET status = 'accepted' WHERE id = ?");
                if ($stmt->execute([$order_id])) {
                    // Add tracking
                    $stmt = $db->prepare("INSERT INTO order_tracking (order_id, status, notes) VALUES (?, 'accepted', 'Pesanan diterima oleh mitra')");
                    $stmt->execute([$order_id]);
                    
                    // Notify customer
                    sendNotification(
                        $order['customer_id'],
                        'Pesanan Diterima',
                        'Pesanan ' . $order['order_number'] . ' telah diterima oleh ' . $partner['laundry_name'],
                        'order',
                        '../customer/tracking.php?order_id=' . $order_id
                    );
                    
                    $success = 'Pesanan berhasil diterima';
                    header("refresh:1");
                } else {
                    $error = 'Gagal menerima pesanan';
                }
                break;
                
            case 'reject_order':
                $reject_reason = sanitize($_POST['reject_reason']);
                $stmt = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
                if ($stmt->execute([$order_id])) {
                    // Add tracking
                    $stmt = $db->prepare("INSERT INTO order_tracking (order_id, status, notes) VALUES (?, 'cancelled', ?)");
                    $stmt->execute([$order_id, 'Pesanan ditolak: ' . $reject_reason]);
                    
                    // Notify customer
                    sendNotification(
                        $order['customer_id'],
                        'Pesanan Ditolak',
                        'Pesanan ' . $order['order_number'] . ' ditolak. Alasan: ' . $reject_reason,
                        'order',
                        '../customer/tracking.php?order_id=' . $order_id
                    );
                    
                    $success = 'Pesanan berhasil ditolak';
                    header("refresh:1");
                } else {
                    $error = 'Gagal menolak pesanan';
                }
                break;
                
            case 'update_status':
                $new_status = sanitize($_POST['new_status']);
                $notes = sanitize($_POST['notes']);
                
                $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
                if ($stmt->execute([$new_status, $order_id])) {
                    // Add tracking
                    $stmt = $db->prepare("INSERT INTO order_tracking (order_id, status, notes) VALUES (?, ?, ?)");
                    $stmt->execute([$order_id, $new_status, $notes]);
                    
                    // Notify customer
                    $status_labels = [ // This is local, but use global one below
                        'picked_up' => 'Cucian Anda telah dijemput',
                        'washing' => 'Cucian sedang dalam proses pencucian',
                        'drying' => 'Cucian sedang dalam proses pengeringan',
                        'ironing' => 'Cucian sedang dalam proses penyetrikaan',
                        'packaging' => 'Cucian sedang dikemas',
                        'delivering' => 'Cucian dalam pengiriman',
                        'completed' => 'Pesanan selesai! Cucian telah diterima'
                    ];
                    
                    if (isset($status_labels[$new_status])) {
                        sendNotification(
                            $order['customer_id'],
                            'Update Status Pesanan',
                            $status_labels[$new_status],
                            'order',
                            '../customer/tracking.php?order_id=' . $order_id
                        );
                    }
                    
                    $success = 'Status berhasil diupdate';
                    header("refresh:1");
                } else {
                    $error = 'Gagal mengupdate status';
                }
                break;
                
            case 'update_weight':
                $actual_weight = (float)$_POST['actual_weight'];
                
                // Recalculate total
                $subtotal = $actual_weight * $order['price_per_kg'];
                $delivery_fee = $order['delivery_fee'];
                $total_amount = $subtotal + $delivery_fee;
                
                $stmt = $db->prepare("
                    UPDATE orders 
                    SET actual_weight = ?, subtotal = ?, total_amount = ?
                    WHERE id = ?
                ");
                if ($stmt->execute([$actual_weight, $subtotal, $total_amount, $order_id])) {
                    // Update payment amount
                    $stmt = $db->prepare("UPDATE payments SET amount = ? WHERE order_id = ?");
                    $stmt->execute([$total_amount, $order_id]);
                    
                    // Notify customer
                    sendNotification(
                        $order['customer_id'],
                        'Berat Cucian Diupdate',
                        'Berat aktual cucian: ' . $actual_weight . ' kg. Total: ' . formatRupiah($total_amount),
                        'order',
                        '../customer/tracking.php?order_id=' . $order_id
                    );
                    
                    $success = 'Berat aktual berhasil diupdate';
                    header("refresh:1");
                } else {
                    $error = 'Gagal mengupdate berat';
                }
                break;
        }
        
        // Refresh order data
        $stmt = $db->prepare("
            SELECT o.*, 
                   u.full_name as customer_name, u.phone as customer_phone, u.email as customer_email,
                   s.service_name, s.price_per_kg,
                   p.status as payment_status, p.payment_method
            FROM orders o
            JOIN users u ON o.customer_id = u.id
            JOIN services s ON o.service_id = s.id
            LEFT JOIN payments p ON o.id = p.order_id
            WHERE o.id = ? AND o.partner_id = ?
        ");
        $stmt->execute([$order_id, $partner_id]);
        $order = $stmt->fetch();
    }
}

// Handle refund action
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='refund_payment') {
    if ($order['payment_status']==='paid') {
        $db->prepare("UPDATE payments SET status='refunded' WHERE order_id=?")->execute([$order_id]);
        $db->prepare("UPDATE orders SET payment_status='refunded' WHERE id=?")->execute([$order_id]);
        // Tracking and notify
        $db->prepare("INSERT INTO order_tracking (order_id, status, notes) VALUES (?, 'paid', 'Pembayaran direfund oleh mitra')")->execute([$order_id]);
        sendNotification($order['customer_id'], 'Refund Diproses', 'Pembayaran untuk pesanan '.$order['order_number'].' telah direfund.', 'payment', '../customer/tracking.php?order_id='.$order_id);
        $success = 'Refund berhasil diproses';
        // Reload order data
        $stmt = $db->prepare("SELECT o.*, u.full_name as customer_name, u.phone as customer_phone, u.email as customer_email, s.service_name, s.price_per_kg, p.status as payment_status, p.payment_method FROM orders o JOIN users u ON o.customer_id = u.id JOIN services s ON o.service_id = s.id LEFT JOIN payments p ON o.id = p.order_id WHERE o.id = ? AND o.partner_id = ?");
        $stmt->execute([$order_id, $partner_id]);
        $order = $stmt->fetch();
    } else {
        $error = 'Tidak dapat refund: pembayaran belum berstatus paid';
    }
}

// Status configuration - PERBAIKAN: Tambah 'paid'
$status_labels = [
    'pending' => 'Menunggu',
    'accepted' => 'Pesanan Diterima',
    'paid' => 'Sudah Dibayar', // Tambahan untuk fix error undefined key 'paid'
    'picked_up' => 'Dijemput Driver',
    'washing' => 'Proses Pencucian',
    'drying' => 'Proses Pengeringan',
    'ironing' => 'Proses Penyetrikaan',
    'packaging' => 'Sedang Dikemas',
    'delivering' => 'Dalam Pengiriman',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan'
];

$next_status = [
    'pending' => 'accepted',
    'accepted' => 'paid', // Asumsi flow setelah accept adalah paid
    'paid' => 'picked_up', // Tambahan untuk flow setelah paid
    'picked_up' => 'washing',
    'washing' => 'drying',
    'drying' => 'ironing',
    'ironing' => 'packaging',
    'packaging' => 'delivering',
    'delivering' => 'completed'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - <?php echo htmlspecialchars($order['order_number']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.3s ease; } /* PERBAIKAN: Tambah shadow dan transisi */
        .card:hover { box-shadow: 0 6px 12px rgba(0,0,0,0.15); }
        .btn { border-radius: 12px; transition: all 0.2s ease; }
        .btn:hover { transform: translateY(-1px); }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .card { box-shadow: none; border: 1px solid #e5e7eb; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen"> <!-- PERBAIKAN: Tambah min-h-screen untuk layout full -->
    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50 no-print">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center">
                <a href="dashboard.php" class="mr-4">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Detail Pesanan</h1>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($order['order_number']); ?></p>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Ringkasan Pesanan -->
        <div class="bg-white card shadow-md p-6 mb-6">
            <h3 class="font-bold text-gray-800 mb-4">Ringkasan Pesanan</h3>
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-600">Tanggal Pesanan</span>
                    <span class="font-semibold"><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Layanan</span>
                    <span class="font-semibold"><?php echo htmlspecialchars($order['service_name']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Harga per Kg</span>
                    <span class="font-semibold"><?php echo formatRupiah($order['price_per_kg']); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Berat</span>
                    <?php if ($order['actual_weight']): ?>
                        <span class="font-semibold">Aktual: <?php echo $order['actual_weight']; ?> Kg</span>
                        <?php if (in_array($order['status'], ['accepted', 'paid', 'picked_up'])): ?> <!-- Kondisi tampil button -->
                            <button onclick="showWeightModal()" class="ml-2 text-purple-600 hover:text-purple-800 font-semibold text-sm">
                                Edit Berat
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="font-semibold">Estimasi: <?php echo $order['estimated_weight']; ?> Kg</span>
                        <?php if (in_array($order['status'], ['accepted', 'paid', 'picked_up'])): ?> <!-- Kondisi tampil button -->
                            <button onclick="showWeightModal()" class="ml-2 text-purple-600 hover:text-purple-800 font-semibold text-sm flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                Set Berat Aktual
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Subtotal</span>
                    <span class="font-semibold"><?php echo formatRupiah($order['subtotal']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Biaya Antar Jemput</span>
                    <span class="font-semibold"><?php echo formatRupiah($order['delivery_fee']); ?></span>
                </div>
                <div class="flex justify-between text-lg font-bold text-purple-600">
                    <span>Total</span>
                    <span><?php echo formatRupiah($order['total_amount']); ?></span>
                </div>
                <?php if ($order['payment_status']==='paid' && !in_array($order['status'], ['cancelled','completed'])): ?>
                <form method="POST" action="" onsubmit="return confirm('Yakin refund pembayaran ini?');" class="mt-4">
                    <input type="hidden" name="action" value="refund_payment">
                    <button class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-md">Refund Pembayaran</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Informasi Customer dan lain-lain tetap sama seperti asli -->
        <!-- ... (kode bagian lain seperti Informasi Customer, Status Pembayaran, Quick Actions, Timeline Tracking tetap seperti asli) ... -->

    </div>

    <!-- Reject Order Modal (tetap sama) -->
    <div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <!-- ... (kode modal reject tetap) ... -->
    </div>

    <!-- Update Weight Modal - PERBAIKAN: Tambah preview total -->
    <div id="weightModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-800">Set Berat Aktual</h3>
                <button onclick="closeWeightModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="update_weight">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Berat Aktual (kg) *</label>
                    <input type="number" id="actual_weight" name="actual_weight" step="0.1" min="0.1" required
                        value="<?php echo $order['actual_weight'] ?: $order['estimated_weight']; ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        placeholder="1.5">
                    <p class="text-xs text-gray-500 mt-1">Estimasi: <?php echo $order['estimated_weight']; ?> kg</p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-gray-700">
                        <strong>Preview Total:</strong> <span id="preview_total"><?php echo formatRupiah($order['total_amount']); ?></span>
                    </p>
                    <p class="text-xs text-gray-500 mt-1">Total dihitung ulang berdasarkan berat aktual. Customer akan dinotifikasi.</p>
                </div>

                <div class="flex space-x-3">
                    <button type="button" onclick="closeWeightModal()" 
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 btn transition duration-200">
                        Batal
                    </button>
                    <button type="submit" 
                        class="flex-1 bg-purple-500 hover:bg-purple-600 text-white font-semibold py-3 btn transition duration-200">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions (tetap sama)
        function showRejectModal() {
            document.getElementById('rejectModal').classList.remove('hidden');
        }
        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
        }
        function showWeightModal() {
            document.getElementById('weightModal').classList.remove('hidden');
        }
        function closeWeightModal() {
            document.getElementById('weightModal').classList.add('hidden');
        }

        // Close modals on outside click (tetap sama)
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) closeRejectModal();
        });
        document.getElementById('weightModal').addEventListener('click', function(e) {
            if (e.target === this) closeWeightModal();
        });

        // PERBAIKAN: Preview total di modal weight
        const pricePerKg = <?php echo $order['price_per_kg']; ?>;
        const deliveryFee = <?php echo $order['delivery_fee']; ?>;
        const actualWeightInput = document.getElementById('actual_weight');
        const previewTotal = document.getElementById('preview_total');

        if (actualWeightInput) {
            actualWeightInput.addEventListener('input', function() {
                const weight = parseFloat(this.value) || 0;
                const subtotal = weight * pricePerKg;
                const total = subtotal + deliveryFee;
                previewTotal.textContent = 'Rp ' + total.toLocaleString('id-ID');
            });
        }
    </script>
</body>
</html>