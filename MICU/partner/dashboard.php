<?php
session_start();
require_once '../config.php';

// PERBAIKAN: Ganti isLoggedIn() dengan pengecekan session langsung
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'partner') {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
$db = db();

// Get partner info
$stmt = $db->prepare("SELECT * FROM laundry_partners WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$partner = $stmt->fetch();

if (!$partner) {
    header('Location: login.php');
    exit;
}

$partner_id = $partner['id'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    $allowed = [
        'waiting_payment' => ['paid'],
        'paid'           => ['processing'],
        'processing'     => ['pickup', 'done'],
        'pickup'         => ['done']
    ];

    // Cek order milik partner
    $stmt = $db->prepare("SELECT status FROM orders WHERE id = ? AND partner_id = ?");
    $stmt->execute([$order_id, $partner_id]);
    $order = $stmt->fetch();

    if ($order && isset($allowed[$order['status']]) && in_array($new_status, $allowed[$order['status']])) {
        $db->beginTransaction();

        $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);

        $notes = "Status diubah ke " . [
            'paid' => 'Dibayar',
            'processing' => 'Diproses',
            'pickup' => 'Diantar',
            'done' => 'Selesai'
        ][$new_status];

        $stmt = $db->prepare("INSERT INTO order_tracking (order_id, status, notes) VALUES (?, ?, ?)");
        $stmt->execute([$order_id, $new_status, $notes]);

        // Notifikasi customer
        $customer_stmt = $db->prepare("SELECT customer_id FROM orders WHERE id = ?");
        $customer_stmt->execute([$order_id]);
        $customer_id = $customer_stmt->fetchColumn();

        $msg = [
            'paid' => 'Pembayaran Anda telah dikonfirmasi!',
            'processing' => 'Cucian sedang diproses.',
            'pickup' => 'Driver akan segera Mengantar cucian.',
            'done' => 'Pesanan selesai!'
        ][$new_status];

        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'order', ?)");
        $stmt->execute([
            $customer_id,
            'Status Diperbarui',
            $msg,
            '../customer/tracking.php?order_id=' . $order_id
        ]);

        $db->commit();
        $_SESSION['flash_success'] = "Status diubah ke: " . ucfirst($new_status);
    } else {
        $_SESSION['flash_error'] = 'Transisi status tidak diizinkan.';
    }

    $filter = $_GET['filter'] ?? 'all';
    redirect("partner/dashboard.php?filter=" . urlencode($filter));
}

// Get statistics
$today = date('Y-m-d');
$this_month = date('Y-m');

$stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE partner_id = ? AND DATE(created_at) = ?");
$stmt->execute([$partner_id, $today]);
$orders_today = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE partner_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?");
$stmt->execute([$partner_id, $this_month]);
$orders_month = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT SUM(total_amount) as total FROM orders WHERE partner_id = ? AND status = 'done' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
$stmt->execute([$partner_id, $this_month]);
$revenue_month = $stmt->fetch()['total'] ?? 0;

// Get recent orders
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_clause = "o.partner_id = ?";
$params = [$partner_id];

if ($filter !== 'all') {
    $where_clause .= " AND o.status = ?";
    $params[] = $filter;
}

$stmt = $db->prepare("
    SELECT o.*, u.full_name as customer_name, u.phone as customer_phone,
           s.service_name
    FROM orders o
    JOIN users u ON o.customer_id = u.id 
    JOIN services s ON o.service_id = s.id
    WHERE $where_clause
    ORDER BY o.created_at DESC
    LIMIT 20
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get unread notifications count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_notifications = $stmt->fetch()['total'];

// Status labels
$status_labels = [
    'pending' => 'Menunggu',
    'waiting_payment' => 'Menunggu Pembayaran',
    'paid' => 'Dibayar',
    'processing' => 'Diproses',
    'pickup' => 'Diantar',
    'done' => 'Selesai',
    'cancelled' => 'Dibatalkan'
];

$status_colors = [
    'pending' => 'gray',
    'waiting_payment' => 'orange',
    'paid' => 'blue',
    'processing' => 'purple',
    'pickup' => 'indigo',
    'done' => 'green',
    'cancelled' => 'red'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($partner['laundry_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { border-radius: 12px; }
        .btn { border-radius: 12px; }
        .status-dropdown { min-width: 140px; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- DEBUG -->
<div class="bg-yellow-100 p-2 text-xs">
    User ID: <?= $_SESSION['user_id'] ?> | 
    Partner ID: <?= $partner_id ?>
</div>
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="fixed top-4 right-4 z-50 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-lg max-w-sm">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <?php echo htmlspecialchars($_SESSION['flash_success']); ?>
            </div>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="fixed top-4 right-4 z-50 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg shadow-lg max-w-sm">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <?php echo htmlspecialchars($_SESSION['flash_error']); ?>
            </div>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($partner['laundry_name']); ?></h1>
                        <p class="text-xs text-gray-500">Partner Dashboard</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Profile Button - BARU -->
                    <a href="profile.php" class="text-gray-600 hover:text-purple-600 transition-colors duration-200" title="Profil">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </a>
                    
                    <!-- Notification Button -->
                    <a href="notifications.php" class="relative text-gray-600 hover:text-purple-600 transition-colors duration-200" title="Notifikasi">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-semibold">
                                <?php echo $unread_notifications; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Logout Button -->
                    <a href="../logout.php" class="text-gray-600 hover:text-red-600 transition-colors duration-200" title="Keluar">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v-1a3 3 0 01-3-3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Statistics -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <!-- Today's Orders -->
            <div class="bg-white card shadow-md p-6">
                <div class="flex items-center mb-2">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-700">Pesanan Hari Ini</h3>
                </div>
                <p class="text-3xl font-bold text-gray-800"><?php echo $orders_today; ?></p>
            </div>

            <!-- This Month's Orders -->
            <div class="bg-white card shadow-md p-6">
                <div class="flex items-center mb-2">
                    <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-700">Pesanan Bulan Ini</h3>
                </div>
                <p class="text-3xl font-bold text-gray-800"><?php echo $orders_month; ?></p>
            </div>

            <!-- Revenue This Month -->
            <div class="bg-white card shadow-md p-6">
                <div class="flex items-center mb-2">
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-700">Pendapatan Bulan Ini</h3>
                </div>
                <p class="text-3xl font-bold text-gray-800">Rp <?php echo number_format($revenue_month, 0, ',', '.'); ?></p>
            </div>
        </div>

        <!-- Orders List -->
        <div class="bg-white card shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="font-bold text-gray-800 text-lg">Daftar Pesanan</h3>
                
                <!-- Filter -->
                <select onchange="window.location.href='?filter='+this.value" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Semua</option>
                    <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                    <option value="paid" <?php echo $filter === 'paid' ? 'selected' : ''; ?>>Dibayar</option>
                    <option value="pickup" <?php echo $filter === 'pickup' ? 'selected' : ''; ?>>Diantar</option>
                    <option value="processing" <?php echo $filter === 'processing' ? 'selected' : ''; ?>>Diproses</option>
                    <option value="done" <?php echo $filter === 'done' ? 'selected' : ''; ?>>Selesai</option>
                    <option value="cancelled" <?php echo $filter === 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                </select>
            </div>

            <?php if (empty($orders)): ?>
                <div class="text-center py-12">
                    <svg class="w-20 h-20 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-gray-500">Belum ada pesanan</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">No. Pesanan</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">Customer</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">Layanan</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">Berat</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">Total</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">Status</th>
                                <th class="text-center py-3 px-4 font-semibold text-gray-700">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): 
                                $status = !empty($order['status']) ? $order['status'] : 'pending';
                            ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['order_number']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                                    </td>
                                    <td class="py-3 px-4">
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($order['customer_phone']); ?></p>
                                    </td>
                                    <td class="py-3 px-4 text-gray-700"><?php echo htmlspecialchars($order['service_name']); ?></td>
                                    <td class="py-3 px-4 text-gray-700"><?php echo $order['estimated_weight']; ?> kg</td>
                                    <td class="py-3 px-4 font-semibold text-gray-800">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                                    <td class="py-3 px-4">
                                        <?php 
                                        $label = $status_labels[$status] ?? 'Unknown';
                                        $color = $status_colors[$status] ?? 'gray';
                                        ?>
                                        <span class="px-3 py-1 bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-700 text-xs font-semibold rounded-full">
                                            <?php echo $label; ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <?php if ($order['status'] === 'pending'): ?>
                                        <a href="confirm-order.php?id=<?= $order['id'] ?>" 
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Konfirmasi Berat
                                        </a>
                                    <?php elseif (in_array($status, ['waiting_payment', 'paid', 'processing', 'pickup'])): ?>
                                        <form method="POST" class="inline" id="status-form-<?= $order['id'] ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <select name="new_status" 
                                                    class="status-dropdown border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 bg-white"
                                                    onchange="if(confirm('Ubah status?')) this.form.submit();">
                                                <option value="" disabled selected><?= $label ?></option>
                                                <?php
                                                // Flow status yang diizinkan
                                                $allowed = [
                                                    'waiting_payment' => ['paid'],
                                                    'paid'           => ['processing'],
                                                    'processing'     => ['pickup', 'done'],
                                                    'pickup'         => ['done']
                                                ];

                                                $options = $allowed[$status] ?? [];
                                                foreach ($options as $val):
                                                    $txt = [
                                                        'paid' => 'Dibayar',
                                                        'processing' => 'Diproses',
                                                        'pickup' => 'Diantar',
                                                        'done' => 'Selesai'
                                                    ][$val];
                                                ?>
                                                    <option value="<?= $val ?>"><?= $txt ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    <?php elseif ($status !== 'cancelled'): ?>
                                        <a href="order-detail.php?id=<?= $order['id'] ?>" class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                                            Detail
                                        </a>
                                    <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-hide flash messages
        setTimeout(function() {
            const flashMessages = document.querySelectorAll('.fixed.top-4.right-4');
            flashMessages.forEach(function(msg) {
                msg.style.transition = 'opacity 0.5s ease-out';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>