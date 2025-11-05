<?php
session_start();
require_once '../config.php';
require_once '../helpers.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$customer_id = $_SESSION['user_id'];

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header('Location: pesanan.php');
    exit;
}

$order_id = intval($_GET['order_id']);

// Get order details
$orderQuery = $conn->query("
    SELECT 
        o.*,
        s.service_name,
        lp.laundry_name,
        lp.laundry_address,
        u.full_name as customer_name,
        u.email as customer_email,
        u.phone as customer_phone
    FROM orders o
    INNER JOIN services s ON o.service_id = s.id
    INNER JOIN laundry_partners lp ON o.partner_id = lp.id
    INNER JOIN users u ON o.customer_id = u.id
    WHERE o.id = $order_id 
    AND o.customer_id = $customer_id
    AND o.payment_status = 'paid'
");

if ($orderQuery->num_rows === 0) {
    $_SESSION['error_message'] = 'Pesanan tidak ditemukan atau belum dibayar';
    header('Location: pesanan.php');
    exit;
}

$order = $orderQuery->fetch_assoc();
// Di dalam proses upload bukti bayar (setelah insert ke tabel payments)
$order_id = (int)$_POST['order_id'];

// Update status order jadi 'processing'
$db = db();
$stmt = $db->prepare("UPDATE orders SET status = 'processing' WHERE id = ? AND customer_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);

// Tambah tracking
$stmt = $db->prepare("INSERT INTO order_tracking (order_id, status, notes) VALUES (?, 'processing', 'Pembayaran dikonfirmasi')");
$stmt->execute([$order_id]);

// Notifikasi ke partner
$stmt = $db->prepare("SELECT partner_id FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$partner_id = $stmt->fetchColumn();

sendNotification(
    $partner_id,
    'Pembayaran Diterima!',
    "Order #{$order_id} telah dibayar. Silakan proses cucian.",
    'order',
    '../partner/dashboard.php'
);
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Berhasil - MICU Laundry</title>
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

        /* Animations */
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

        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes checkmark {
            0% {
                stroke-dashoffset: 100;
            }
            100% {
                stroke-dashoffset: 0;
            }
        }

        @keyframes confetti {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(1000px) rotate(720deg);
                opacity: 0;
            }
        }

        /* Container */
        .success-container {
            max-width: 800px;
            margin: 0 auto;
        }

        /* Success Card */
        .success-card {
            background: white;
            border-radius: 25px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            text-align: center;
            animation: fadeInUp 0.8s ease-out;
            position: relative;
            overflow: hidden;
        }

        /* Success Icon */
        .success-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
            position: relative;
            animation: scaleIn 0.6s ease-out 0.3s backwards;
        }

        .checkmark-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 40px rgba(76, 175, 80, 0.4);
        }

        .checkmark {
            width: 60px;
            height: 60px;
            stroke: white;
            stroke-width: 4;
            stroke-linecap: round;
            stroke-dasharray: 100;
            stroke-dashoffset: 0;
            animation: checkmark 0.8s ease-out 0.5s backwards;
        }

        /* Success Message */
        .success-title {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            animation: fadeInUp 0.8s ease-out 0.6s backwards;
        }

        .success-subtitle {
            font-size: 18px;
            color: #666;
            margin-bottom: 40px;
            line-height: 1.6;
            animation: fadeInUp 0.8s ease-out 0.7s backwards;
        }

        /* Order Details */
        .order-details {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: left;
            animation: fadeInUp 0.8s ease-out 0.8s backwards;
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .order-number {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
        }

        .status-badge {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-size: 15px;
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            font-size: 15px;
            color: #333;
            font-weight: 600;
            text-align: right;
        }

        .total-row {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #667eea;
        }

        .total-row .detail-label,
        .total-row .detail-value {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
        }

        /* Info Box */
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
            animation: fadeInUp 0.8s ease-out 0.9s backwards;
        }

        .info-box-title {
            font-size: 16px;
            font-weight: 700;
            color: #004085;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box-content {
            font-size: 14px;
            color: #004085;
            line-height: 1.8;
        }

        .info-box-content ol {
            margin-left: 20px;
            margin-top: 10px;
        }

        .info-box-content li {
            margin-bottom: 8px;
        }

        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
            animation: fadeInUp 0.8s ease-out 1s backwards;
        }

        .btn {
            padding: 16px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        /* Download Button */
        .download-receipt {
            text-align: center;
            margin-top: 30px;
            animation: fadeInUp 0.8s ease-out 1.1s backwards;
        }

        .download-link {
            color: #667eea;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .download-link:hover {
            gap: 12px;
            color: #764ba2;
        }

        /* Confetti Effect */
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #667eea;
            animation: confetti 3s ease-out forwards;
        }

        .confetti:nth-child(1) { left: 10%; background: #667eea; animation-delay: 0s; }
        .confetti:nth-child(2) { left: 20%; background: #4caf50; animation-delay: 0.1s; }
        .confetti:nth-child(3) { left: 30%; background: #ff9800; animation-delay: 0.2s; }
        .confetti:nth-child(4) { left: 40%; background: #f44336; animation-delay: 0.3s; }
        .confetti:nth-child(5) { left: 50%; background: #2196f3; animation-delay: 0.4s; }
        .confetti:nth-child(6) { left: 60%; background: #e91e63; animation-delay: 0.5s; }
        .confetti:nth-child(7) { left: 70%; background: #9c27b0; animation-delay: 0.6s; }
        .confetti:nth-child(8) { left: 80%; background: #00bcd4; animation-delay: 0.7s; }
        .confetti:nth-child(9) { left: 90%; background: #ffeb3b; animation-delay: 0.8s; }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .success-card {
                padding: 30px 20px;
            }

            .success-title {
                font-size: 28px;
            }

            .success-subtitle {
                font-size: 16px;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .order-details {
                padding: 20px;
            }

            .detail-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <!-- Confetti Effect -->
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>

            <!-- Success Icon -->
            <div class="success-icon">
                <div class="checkmark-circle">
                    <svg class="checkmark" viewBox="0 0 52 52">
                        <path fill="none" d="M14 27l7.5 7.5L38 18"/>
                    </svg>
                </div>
            </div>

            <!-- Success Message -->
            <h1 class="success-title">Pembayaran Berhasil!</h1>
            <p class="success-subtitle">
                Terima kasih telah melakukan pembayaran.<br>
                Pesanan Anda sedang diproses oleh mitra laundry.
            </p>

            <!-- Order Details -->
            <div class="order-details">
                <div class="detail-header">
                    <div class="order-number">
                        <i class="fas fa-receipt"></i>
                        <?php echo htmlspecialchars($order['order_number']); ?>
                    </div>
                    <div class="status-badge">
                        <i class="fas fa-check-circle"></i>
                        Pembayaran Berhasil
                    </div>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Tanggal Pembayaran</span>
                    <span class="detail-value">
                        <?php echo date('d M Y, H:i', strtotime($order['payment_date'])); ?>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Mitra Laundry</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['laundry_name']); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Layanan</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['service_name']); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Alamat Pickup</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['pickup_address']); ?></span>
                </div>

                <?php if ($order['delivery_fee'] > 0): ?>
                <div class="detail-row">
                    <span class="detail-label">Subtotal</span>
                    <span class="detail-value">Rp <?php echo number_format($order['subtotal'], 0, ',', '.'); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Biaya Antar Jemput</span>
                    <span class="detail-value">Rp <?php echo number_format($order['delivery_fee'], 0, ',', '.'); ?></span>
                </div>
                <?php endif; ?>

                <div class="detail-row total-row">
                    <span class="detail-label">Total Dibayar</span>
                    <span class="detail-value">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                </div>
            </div>

            <!-- Info Box -->
            <div class="info-box">
                <div class="info-box-title">
                    <i class="fas fa-info-circle"></i>
                    Langkah Selanjutnya
                </div>
                <div class="info-box-content">
                    <ol>
                        <li>Mitra laundry akan memverifikasi pembayaran Anda</li>
                        <li>Setelah verifikasi, driver akan menjemput cucian Anda sesuai waktu yang telah ditentukan</li>
                        <li>Anda dapat melacak status pesanan secara real-time</li>
                        <li>Notifikasi akan dikirim untuk setiap perubahan status pesanan</li>
                    </ol>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="tracking.php?order_id=<?php echo $order['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-map-marked-alt"></i>
                    <span>Lacak Pesanan</span>
                </a>

                <a href="pesanan.php" class="btn btn-secondary">
                    <i class="fas fa-history"></i>
                    <span>Lihat Riwayat</span>
                </a>
            </div>

            <div class="action-buttons">
                <a href="home.php" class="btn btn-success">
                    <i class="fas fa-home"></i>
                    <span>Kembali ke Home</span>
                </a>

                <a href="order.php?partner_id=<?php echo $order['partner_id']; ?>" class="btn btn-secondary">
                    <i class="fas fa-redo"></i>
                    <span>Pesan Lagi</span>
                </a>
            </div>

            <!-- Download Receipt -->
            <div class="download-receipt">
                <a href="download_receipt.php?order_id=<?php echo $order['id']; ?>" class="download-link">
                    <i class="fas fa-download"></i>
                    <span>Download Bukti Pembayaran (PDF)</span>
                </a>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Auto redirect to tracking after 10 seconds (optional)
        // setTimeout(() => {
        //     window.location.href = 'tracking.php?order_id=<?php echo $order['id']; ?>';
        // }, 10000);

        // Confetti animation
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Pembayaran berhasil untuk order #<?php echo $order['order_number']; ?>');
        });

        // Prevent back button after successful payment
        window.history.pushState(null, "", window.location.href);
        window.onpopstate = function() {
            window.history.pushState(null, "", window.location.href);
        };
    </script>
</body>
</html>