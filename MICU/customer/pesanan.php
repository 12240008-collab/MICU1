<?php
// Hapus badge pesanan setelah dibuka
if (isset($_GET['tab']) || isset($_GET['highlight'])) {
    // Tidak perlu update DB, cukup tidak tampilkan badge di header
}
?>

<?php
session_start();
require_once '../config.php';
require_once '../helpers.php';

usleep(800000);
// Cek login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$customer_id = $_SESSION['user_id'];

// Handle cancel order
if (isset($_POST['cancel_order'])) {
    $order_id = intval($_POST['order_id']);
    
    $verifyQuery = $conn->query("SELECT id FROM orders WHERE id = $order_id AND customer_id = $customer_id AND status IN ('pending', 'waiting_payment')");
    
    if ($verifyQuery->num_rows > 0) {
        $conn->query("UPDATE orders SET status = 'cancelled' WHERE id = $order_id");
        $conn->query("INSERT INTO order_tracking (order_id, status, notes) VALUES ($order_id, 'cancelled', 'Pesanan dibatalkan oleh pelanggan')");
        $_SESSION['success_message'] = 'Pesanan berhasil dibatalkan';
    }
    
    header('Location: pesanan.php');
    exit;
}

// Handle hide history
if (isset($_POST['hide_history'])) {
    $order_id = intval($_POST['order_id']);
    $conn->query("UPDATE orders SET hidden_by_customer = 1 WHERE id = $order_id AND customer_id = $customer_id");
    $_SESSION['success_message'] = 'Riwayat berhasil dihapus dari tampilan';
    header('Location: pesanan.php?tab=history');
    exit;
}

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'active';

// Ambil pesanan aktif (pending, waiting_payment, paid, pickup, processing, delivered)
$activeQuery = $conn->query("
    SELECT 
        o.*,
        s.service_name,
        s.estimated_hours,
        lp.laundry_name,
        lp.laundry_address,
        lp.rating
    FROM orders o
    INNER JOIN services s ON o.service_id = s.id
    INNER JOIN laundry_partners lp ON o.partner_id = lp.id
    WHERE o.customer_id = $customer_id
    AND o.status IN ('pending', 'waiting_payment', 'paid', 'pickup', 'processing', 'delivered')
    ORDER BY o.created_at DESC
");

$activeOrders = [];
while ($row = $activeQuery->fetch_assoc()) {
    $activeOrders[] = $row;
}

// Ambil riwayat (done, cancelled)
$historyQuery = $conn->query("
    SELECT 
        o.*,
        s.service_name,
        s.estimated_hours,
        lp.laundry_name,
        lp.laundry_address,
        lp.rating
    FROM orders o
    INNER JOIN services s ON o.service_id = s.id
    INNER JOIN laundry_partners lp ON o.partner_id = lp.id
    WHERE o.customer_id = $customer_id
    AND o.status IN ('done', 'cancelled')
    AND o.hidden_by_customer = 0
    ORDER BY o.updated_at DESC
");

$historyOrders = [];
while ($row = $historyQuery->fetch_assoc()) {
    $historyOrders[] = $row;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - MICU Laundry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .orders-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            animation: fadeInUp 0.6s ease-out;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 15px;
            margin-bottom: 20px;
        }

        .back-button:hover {
            gap: 12px;
            color: #764ba2;
        }

        .back-button i {
            transition: transform 0.3s ease;
        }

        .back-button:hover i {
            transform: translateX(-5px);
        }

        .page-title {
            font-size: 32px;
            color: #333;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .page-title i {
            color: #667eea;
        }

        .page-subtitle {
            color: #666;
            font-size: 15px;
            margin-bottom: 25px;
        }

        /* Main Tabs */
        .main-tabs {
            display: flex;
            gap: 15px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0;
        }

        .main-tab {
            padding: 15px 30px;
            background: transparent;
            border: none;
            color: #666;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            border-radius: 10px 10px 0 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .main-tab:hover {
            background: #f8f9fa;
            color: #667eea;
        }

        .main-tab.active {
            color: #667eea;
            background: #f8f9ff;
        }

        .main-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 3px 3px 0 0;
        }

        .main-tab .badge {
            background: #ff4757;
            color: white;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            margin-left: 4px;
        }

        /* Filter Tabs */
        .filter-section {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            background: white;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-tab:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        /* Tab Content */
        .tab-content {
            display: none;
            animation: fadeInUp 0.6s ease-out;
        }

        .tab-content.active {
            display: block;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeInUp 0.6s ease-out;
        }

        .alert-success {
            background: #d4edda;
            border: 2px solid #c3e6cb;
            color: #155724;
        }

        .alert i {
            font-size: 20px;
        }

        .orders-list {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }

        .order-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease-out backwards;
        }

        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .order-card:nth-child(1) { animation-delay: 0.1s; }
        .order-card:nth-child(2) { animation-delay: 0.2s; }
        .order-card:nth-child(3) { animation-delay: 0.3s; }
        .order-card:nth-child(4) { animation-delay: 0.4s; }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .order-info {
            flex: 1;
        }

        .order-number {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .order-date {
            color: #666;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .order-date i {
            color: #667eea;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-waiting_payment { 
            background: #ffeaa7; 
            color: #d63031;
            animation: pulse 2s ease-in-out infinite;
        }
        .status-paid { background: #d1ecf1; color: #0c5460; }
        .status-pickup { background: #d4edda; color: #155724; }
        .status-processing { background: #cfe2ff; color: #084298; }
        .status-delivered { background: #d1f2eb; color: #0c5460; }
        .status-done { background: #d1e7dd; color: #0f5132; }
        .status-cancelled { background: #f8d7da; color: #842029; }

        .order-body {
            display: grid;
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            gap: 15px;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f3ff 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .info-icon i {
            color: #667eea;
            font-size: 18px;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 15px;
            color: #333;
            font-weight: 600;
        }

        .price-summary {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .price-row:last-child {
            margin-bottom: 0;
        }

        .price-row.total {
            border-top: 2px solid #dee2e6;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
        }

        .price-pending {
            color: #856404;
            font-style: italic;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-note {
            background: #e7f3ff;
            border: 2px solid #b3d9ff;
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #004085;
            line-height: 1.5;
        }

        .info-note i {
            font-size: 18px;
            flex-shrink: 0;
        }

        .info-note.warning {
            background: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }

        .info-note.warning i {
            color: #d63031;
            animation: pulse 2s ease-in-out infinite;
        }

        .order-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            flex: 1;
            min-width: 150px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.4);
        }

        .btn-outline {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn:disabled:hover {
            transform: none;
            box-shadow: none;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            animation: fadeInUp 0.6s ease-out;
        }

        .empty-state i {
            font-size: 80px;
            color: rgba(255, 255, 255, 0.3);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: white;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 16px;
            margin-bottom: 30px;
        }

        .btn-white {
            background: white;
            color: #667eea;
            padding: 15px 30px;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-white:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.3);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease-out;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            animation: fadeInUp 0.3s ease-out;
        }

        .modal-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .modal-icon {
            width: 60px;
            height: 60px;
            background: #fee;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-icon i {
            font-size: 30px;
            color: #f44336;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }

        .modal-body {
            margin-bottom: 25px;
            color: #666;
            line-height: 1.6;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .page-header {
                padding: 20px;
            }

            .page-title {
                font-size: 24px;
            }

            .main-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                -webkit-overflow-scrolling: touch;
            }

            .main-tab {
                flex-shrink: 0;
                padding: 12px 20px;
            }

            .order-card {
                padding: 20px;
            }

            .order-header {
                flex-direction: column;
                gap: 15px;
            }

            .order-actions {
                flex-direction: column;
            }

            .btn {
                min-width: 100%;
            }

            .filter-section {
                overflow-x: auto;
                flex-wrap: nowrap;
            }

            .filter-tab {
                flex-shrink: 0;
            }
        }
    </style>
</head>

<?php
// Highlight order dari notifikasi
$highlight_order_id = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Highlight dari notifikasi
    const highlightId = sessionStorage.getItem('highlightOrderId');
    if (highlightId) {
        const target = document.querySelector(`#order-${highlightId}`);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.style.animation = 'highlightPulse 2s ease-out';
            target.style.boxShadow = '0 0 20px rgba(102, 126, 234, 0.6)';
            setTimeout(() => {
                target.style.boxShadow = '';
                target.style.animation = '';
            }, 2000);
        }
        sessionStorage.removeItem('highlightOrderId');
    }
});

// Tambahkan animasi highlight
const style = document.createElement('style');
style.textContent = `
    @keyframes highlightPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.02); }
    }
`;
document.head.appendChild(style);
</script>

<body>
    <div class="orders-container">
        <div class="page-header">
            <a href="home.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                <span>Kembali</span>
            </a>
            
            <h1 class="page-title">
                <i class="fas fa-box"></i>
                Pesanan Saya
            </h1>
            <p class="page-subtitle">Kelola dan pantau semua pesanan laundry Anda</p>

            <!-- Main Tabs -->
            <div class="main-tabs">
                <button class="main-tab <?php echo $active_tab == 'active' ? 'active' : ''; ?>" onclick="switchTab('active')">
                    <i class="fas fa-clock"></i>
                    <span>Pesanan Aktif</span>
                    <?php if (count($activeOrders) > 0): ?>
                    <span class="badge"><?php echo count($activeOrders); ?></span>
                    <?php endif; ?>
                </button>
                <button class="main-tab <?php echo $active_tab == 'history' ? 'active' : ''; ?>" onclick="switchTab('history')">
                    <i class="fas fa-history"></i>
                    <span>Riwayat</span>
                    <?php if (count($historyOrders) > 0): ?>
                    <span class="badge"><?php echo count($historyOrders); ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
        </div>
        <?php endif; ?>

        <!-- Active Orders Tab -->
        <div class="tab-content <?php echo $active_tab == 'active' ? 'active' : ''; ?>" id="activeTab">
            <div class="filter-section">
                <button class="filter-tab active" data-filter="all">
                    <i class="fas fa-list"></i> Semua
                </button>
                <button class="filter-tab" data-filter="pending">
                    <i class="fas fa-clock"></i> Menunggu
                </button>
                <button class="filter-tab" data-filter="waiting_payment">
                    <i class="fas fa-exclamation-circle"></i> Perlu Bayar
                </button>
                <button class="filter-tab" data-filter="paid">
                    <i class="fas fa-check-circle"></i> Dibayar
                </button>
                <button class="filter-tab" data-filter="processing">
                    <i class="fas fa-spinner"></i> Diproses
                </button>
                <button class="filter-tab" data-filter="pickup">
                    <i class="fas fa-shipping-fast"></i> Diantar
                </button>
            </div>

            <?php if (count($activeOrders) > 0): ?>
            <div class="orders-list">
                <?php foreach ($activeOrders as $order): 
                    $statusClass = 'status-' . $order['status'];
                    $statusLabels = [
                        'pending' => 'Menunggu Konfirmasi',
                        'waiting_payment' => 'Menunggu Pembayaran',
                        'paid' => 'Sudah Dibayar',
                        'processing' => 'Sedang Diproses',
                        'pickup' => 'Laundry Diantar'
                    ];
                    $statusIcons = [
                        'pending' => 'fa-clock',
                        'waiting_payment' => 'fa-exclamation-circle',
                        'paid' => 'fa-check-circle',
                        'pickup' => 'fa-truck',
                        'processing' => 'fa-spinner',
                        'delivered' => 'fa-shipping-fast'
                    ];
                    
                    // Check if tracking available (paid, pickup, processing, or delivered)
                    $canTrack = in_array($order['status'], ['paid', 'pickup', 'processing', 'delivered']);
                ?>
                <div class="order-card" data-status="<?php echo $order['status']; ?>" id="order-<?php echo $order['id']; ?>">
                    <div class="order-header">
                        <div class="order-info">
                            <div class="order-number"><?php echo htmlspecialchars($order['order_number']); ?></div>
                            <div class="order-date">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?>
                            </div>
                        </div>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <i class="fas <?php echo $statusIcons[$order['status']]; ?>"></i>
                            <?php echo $statusLabels[$order['status']]; ?>
                        </span>
                    </div>

                    <div class="order-body">
                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Mitra Laundry</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['laundry_name']); ?></div>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fas fa-tint"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Layanan</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['service_name']); ?></div>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Alamat</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['pickup_address']); ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if ($order['status'] == 'pending'): ?>
                    <div class="info-note">
                        <i class="fas fa-info-circle"></i>
                        <span>Menunggu mitra laundry menjemput dan menimbang cucian Anda. Pembayaran akan tersedia setelah penimbangan.</span>
                    </div>
                    <?php elseif ($order['status'] == 'waiting_payment'): ?>
                    <div class="info-note warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Cucian Anda sudah ditimbang! Silakan lakukan pembayaran untuk melanjutkan proses laundry.</span>
                    </div>
                    <?php endif; ?>

                    <div class="price-summary">
                        <?php if ($order['status'] == 'pending'): ?>
                        <div class="price-row total">
                            <span>Total Pembayaran</span>
                            <span class="price-pending">
                                <i class="fas fa-hourglass-half"></i>
                                Menunggu Penimbangan
                            </span>
                        </div>
                        <?php else: ?>
                        <?php if ($order['delivery_fee'] > 0): ?>
                        <div class="price-row">
                            <span>Subtotal</span>
                            <span>Rp<?php echo number_format($order['subtotal'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="price-row">
                            <span>Biaya Antar Jemput</span>
                            <span>Rp<?php echo number_format($order['delivery_fee'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="price-row total">
                            <span>Total</span>
                            <span>Rp<?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="order-actions">
                        <?php if ($canTrack): ?>
                        <a href="tracking.php?order_id=<?php echo $order['id']; ?>" class="btn btn-outline">
                            <i class="fas fa-map-marked-alt"></i>
                            Lacak Pesanan
                        </a>
                        <?php else: ?>
                        <button class="btn btn-outline" disabled title="Tracking tersedia setelah pembayaran">
                            <i class="fas fa-lock"></i>
                            Lacak Pesanan
                        </button>
                        <?php endif; ?>

                        <?php if ($order['status'] == 'waiting_payment'): ?>
                        <a href="payment.php?order_id=<?php echo $order['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-credit-card"></i>
                            Bayar Sekarang
                        </a>
                        <?php endif; ?>

                        <?php if (in_array($order['status'], ['pending', 'waiting_payment'])): ?>
                        <button class="btn btn-danger" onclick="confirmCancel(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')">
                            <i class="fas fa-times"></i>
                            Batalkan
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Belum Ada Pesanan Aktif</h3>
                <p>Anda belum memiliki pesanan yang sedang diproses</p>
                <a href="home.php" class="btn-white">
                    <i class="fas fa-plus"></i>
                    <span>Buat Pesanan Baru</span>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- History Tab -->
        <div class="tab-content <?php echo $active_tab == 'history' ? 'active' : ''; ?>" id="historyTab">
            <div class="filter-section">
                <button class="filter-tab active" data-filter="all">
                    <i class="fas fa-list"></i> Semua
                </button>
                <button class="filter-tab" data-filter="done">
                    <i class="fas fa-check-double"></i> Selesai
                </button>
                <button class="filter-tab" data-filter="cancelled">
                    <i class="fas fa-times-circle"></i> Dibatalkan
                </button>
            </div>

            <?php if (count($historyOrders) > 0): ?>
            <div class="orders-list">
                <?php foreach ($historyOrders as $order): 
                    $statusClass = 'status-' . $order['status'];
                    $statusLabels = [
                        'done' => 'Selesai',
                        'cancelled' => 'Dibatalkan'
                    ];
                    $statusIcons = [
                        'done' => 'fa-check-double',
                        'cancelled' => 'fa-times-circle'
                    ];
                ?>
                <div class="order-card" data-status="<?php echo $order['status']; ?>">
                    <div class="order-header">
                        <div class="order-info">
                            <div class="order-number"><?php echo htmlspecialchars($order['order_number']); ?></div>
                            <div class="order-date">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('d M Y, H:i', strtotime($order['updated_at'])); ?>
                            </div>
                        </div>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <i class="fas <?php echo $statusIcons[$order['status']]; ?>"></i>
                            <?php echo $statusLabels[$order['status']]; ?>
                        </span>
                    </div>

                    <div class="order-body">
                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Mitra Laundry</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['laundry_name']); ?></div>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fas fa-tint"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Layanan</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['service_name']); ?></div>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Alamat</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['pickup_address']); ?></div>
                            </div>
                        </div>

                        <?php if ($order['actual_weight']): ?>
                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fas fa-weight"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Berat Cucian</div>
                                <div class="info-value"><?php echo number_format($order['actual_weight'], 1); ?> kg</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="price-summary">
                        <?php if ($order['delivery_fee'] > 0): ?>
                        <div class="price-row">
                            <span>Subtotal</span>
                            <span>Rp<?php echo number_format($order['subtotal'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="price-row">
                            <span>Biaya Antar Jemput</span>
                            <span>Rp<?php echo number_format($order['delivery_fee'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="price-row total">
                            <span>Total Pembayaran</span>
                            <span>Rp<?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="order-actions">
                        <a href="tracking.php?order_id=<?php echo $order['id']; ?>" class="btn btn-outline">
                            <i class="fas fa-receipt"></i>
                            Detail Pesanan
                        </a>

                        <?php if ($order['status'] == 'done'): ?>
                        <!-- Rating Modal Button -->
                        <button class="btn btn-outline" onclick="showRatingModal(<?php echo $order['id']; ?>, <?php echo $order['partner_id']; ?>, '<?php echo htmlspecialchars($order['laundry_name']); ?>')">
                            <i class="fas fa-star"></i>
                            Beri Rating
                        </button>
                        
                        <a href="order.php?partner_id=<?php echo $order['partner_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-redo"></i>
                            Pesan Lagi
                        </a>
                        <?php endif; ?>

                        <button class="btn btn-danger" onclick="confirmDelete(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')">
                            <i class="fas fa-trash"></i>
                            Hapus
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Belum Ada Riwayat</h3>
                <p>Anda belum memiliki riwayat pesanan yang selesai atau dibatalkan</p>
                <a href="home.php" class="btn-white">
                    <i class="fas fa-plus"></i>
                    <span>Mulai Pesan Laundry</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cancel Order Modal -->
    <div class="modal" id="cancelModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="modal-title">Batalkan Pesanan?</div>
                </div>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin membatalkan pesanan <strong id="cancelOrderNumber"></strong>?</p>
                <p>Tindakan ini tidak dapat dibatalkan.</p>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closeModal('cancelModal')">
                    <i class="fas fa-times"></i>
                    Batal
                </button>
                <form method="POST" style="flex: 1;">
                    <input type="hidden" name="order_id" id="cancelOrderId">
                    <button type="submit" name="cancel_order" class="btn btn-danger" style="width: 100%;">
                        <i class="fas fa-check"></i>
                        Ya, Batalkan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete History Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="modal-title">Hapus Riwayat?</div>
                </div>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus pesanan <strong id="deleteOrderNumber"></strong> dari riwayat?</p>
                <p>Riwayat akan disembunyikan dari tampilan Anda, tetapi data tetap tersimpan di sistem.</p>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closeModal('deleteModal')">
                    <i class="fas fa-times"></i>
                    Batal
                </button>
                <form method="POST" style="flex: 1;">
                    <input type="hidden" name="order_id" id="deleteOrderId">
                    <button type="submit" name="hide_history" class="btn btn-danger" style="width: 100%;">
                        <i class="fas fa-check"></i>
                        Ya, Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Rating Modal -->
    <div class="modal" id="ratingModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="modal-title">Beri Rating</div>
            </div>
            <form id="ratingForm" action="submit_rating.php" method="POST">
                <input type="hidden" name="order_id" id="ratingOrderId">
                <input type="hidden" name="partner_id" id="ratingPartnerId">
                
                <div class="modal-body">
                    <p>Beri rating untuk <strong id="partnerName"></strong></p>
                    <div id="existingRating" style="margin: 10px 0; display: none;">
                        <p class="info-label">Rating Sebelumnya:</p>
                        <div class="previous-rating" style="margin: 5px 0;"></div>
                        <p class="previous-comment" style="font-style: italic; color: #666;"></p>
                    </div>
                    <div class="rating-stars" style="margin: 20px 0; text-align: center;">
                        <i class="fas fa-star" data-rating="1"></i>
                        <i class="fas fa-star" data-rating="2"></i>
                        <i class="fas fa-star" data-rating="3"></i>
                        <i class="fas fa-star" data-rating="4"></i>
                        <i class="fas fa-star" data-rating="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" required>
                    <textarea name="comment" id="ratingComment" placeholder="Berikan komentar Anda (opsional)" 
                        class="rating-comment" style="width: 100%; padding: 10px; margin-top: 10px; border-radius: 8px; border: 1px solid #ddd;"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal('ratingModal')">
                        <i class="fas fa-times"></i>
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        <span id="ratingSubmitText">Kirim Rating</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tab) {
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);

            // Update tab buttons
            document.querySelectorAll('.main-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.closest('.main-tab').classList.add('active');

            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tab + 'Tab').classList.add('active');

            // Reset filter to 'all'
            const activeContent = document.getElementById(tab + 'Tab');
            const filterTabs = activeContent.querySelectorAll('.filter-tab');
            filterTabs.forEach(filter => {
                filter.classList.remove('active');
                if (filter.dataset.filter === 'all') {
                    filter.classList.add('active');
                }
            });

            // Show all cards
            const cards = activeContent.querySelectorAll('.order-card');
            cards.forEach(card => {
                card.style.display = 'block';
            });
        }

        // Filter functionality for both tabs
        document.querySelectorAll('.filter-section').forEach(section => {
            const filterTabs = section.querySelectorAll('.filter-tab');
            
            filterTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Update active filter
                    filterTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');

                    const filter = this.dataset.filter;
                    const parentTab = this.closest('.tab-content');
                    const orderCards = parentTab.querySelectorAll('.order-card');

                    // Filter cards
                    orderCards.forEach(card => {
                        if (filter === 'all') {
                            card.style.display = 'block';
                        } else {
                            if (card.dataset.status === filter) {
                                card.style.display = 'block';
                            } else {
                                card.style.display = 'none';
                            }
                        }
                    });
                });
            });
        });

        // Cancel order modal
        function confirmCancel(orderId, orderNumber) {
            document.getElementById('cancelOrderId').value = orderId;
            document.getElementById('cancelOrderNumber').textContent = orderNumber;
            document.getElementById('cancelModal').classList.add('active');
        }

        // Delete history modal
        function confirmDelete(orderId, orderNumber) {
            document.getElementById('deleteOrderId').value = orderId;
            document.getElementById('deleteOrderNumber').textContent = orderNumber;
            document.getElementById('deleteModal').classList.add('active');
        }

        // Rating modal
        async function showRatingModal(orderId, partnerId, partnerName) {
            document.getElementById('ratingOrderId').value = orderId;
            document.getElementById('ratingPartnerId').value = partnerId;
            document.getElementById('partnerName').textContent = partnerName;
            
            // Fetch existing rating if any
            try {
                const response = await fetch(`get_rating.php?order_id=${orderId}`);
                const data = await response.json();
                
                if (data.hasRating) {
                    document.getElementById('existingRating').style.display = 'block';
                    document.getElementById('ratingInput').value = data.rating;
                    document.getElementById('ratingComment').value = data.comment || '';
                    document.getElementById('ratingSubmitText').textContent = 'Update Rating';
                    
                    // Show previous rating
                    const stars = document.querySelectorAll('.rating-stars i');
                    stars.forEach(star => {
                        if (star.dataset.rating <= data.rating) {
                            star.style.color = '#ffc107';
                        } else {
                            star.style.color = '#ddd';
                        }
                    });
                    
                    // Display previous rating info
                    document.querySelector('.previous-rating').innerHTML = '★'.repeat(data.rating) + '☆'.repeat(5-data.rating);
                    if (data.comment) {
                        document.querySelector('.previous-comment').textContent = `"${data.comment}"`;
                    }
                } else {
                    // Reset form for new rating
                    document.getElementById('existingRating').style.display = 'none';
                    document.getElementById('ratingInput').value = '';
                    document.getElementById('ratingComment').value = '';
                    document.getElementById('ratingSubmitText').textContent = 'Kirim Rating';
                    
                    // Reset stars
                    document.querySelectorAll('.rating-stars i').forEach(star => star.style.color = '#ddd');
                }
            } catch (error) {
                console.error('Error fetching rating:', error);
            }
            
            document.getElementById('ratingModal').classList.add('active');
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal on backdrop click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });

        // Smooth scroll for back button
        document.querySelector('.back-button').addEventListener('click', function(e) {
            e.preventDefault();
            document.body.style.animation = 'fadeIn 0.3s ease-out reverse';
            setTimeout(() => {
                window.location.href = this.href;
            }, 300);
        });

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.animation = 'fadeInUp 0.4s ease-out reverse';
                setTimeout(() => alert.remove(), 400);
            });
        }, 5000);

        // Add ripple effect to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                if (this.disabled) return;
                
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;

                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.6)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple-animation 0.6s ease-out';
                ripple.style.pointerEvents = 'none';

                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);

                setTimeout(() => ripple.remove(), 600);
            });
        });

        // Rating stars functionality
        document.head.insertAdjacentHTML('beforeend', `
  <style>
    .rating-stars { font-size: 28px; display:inline-block; }
    .rating-stars i { cursor: pointer; color: #ddd; transition: color .12s ease; pointer-events: auto; margin: 0 4px; }
    .rating-stars i.active { color: #ffc107; }
  </style>
`);

document.addEventListener('click', function (e) {
  // event delegation: tangkap klik pada elemen <i> di dalam .rating-stars
  const star = e.target.closest('.rating-stars i');
  if (!star) return;

  // pastikan modal rating sedang terbuka
  const modal = document.getElementById('ratingModal');
  if (modal && !modal.classList.contains('active')) return;

  const rating = parseInt(star.getAttribute('data-rating') || 0, 10);
  if (!rating) return;

  const stars = star.parentElement.querySelectorAll('i');
  stars.forEach(s => {
    const r = parseInt(s.getAttribute('data-rating'), 10);
    if (r <= rating) s.classList.add('active');
    else s.classList.remove('active');
  });

  const input = document.getElementById('ratingInput');
  if (input) input.value = rating;
});

// keyboard support: 1-5 and arrow keys saat modal aktif
document.addEventListener('keydown', function (e) {
  const modal = document.getElementById('ratingModal');
  if (!modal || !modal.classList.contains('active')) return;
  const current = parseInt(document.getElementById('ratingInput').value || 0, 10) || 0;

  if (e.key === 'ArrowRight' && current < 5) {
    const next = current + 1;
    const target = document.querySelector(`.rating-stars i[data-rating="${next}"]`);
    if (target) target.click();
  } else if (e.key === 'ArrowLeft' && current > 1) {
    const prev = current - 1;
    const target = document.querySelector(`.rating-stars i[data-rating="${prev}"]`);
    if (target) target.click();
  } else if (/^[1-5]$/.test(e.key)) {
    const target = document.querySelector(`.rating-stars i[data-rating="${e.key}"]`);
    if (target) target.click();
  }
});

// Optional: reset visual when modal opened/closed
document.getElementById('ratingModal').addEventListener('transitionend', () => {
  // noop - placeholder if you want animations
});
    </script>
</body>
</html>