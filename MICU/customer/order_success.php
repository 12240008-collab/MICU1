<?php
session_start();
require_once '../config.php';
require_once '../helpers.php';

if (!isset($_GET['order_id'])) {
    header('Location: home.php');
    exit;
}

$order_id = $_GET['order_id'];

// Fetch order details
$stmt = $conn->prepare("
    SELECT o.*, 
           lp.laundry_name, 
           s.service_name,
           u.full_name as customer_name
    FROM orders o
    JOIN laundry_partners lp ON o.partner_id = lp.id
    JOIN services s ON o.service_id = s.id
    JOIN users u ON o.customer_id = u.id
    WHERE o.id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - MICU Laundry</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --success: #16a34a;
            --warning: #ca8a04;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            min-height: 100vh;
            padding: 2rem;
            color: var(--gray-900);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }

        .header {
            background: var(--gray-50);
            padding: 2rem;
            border-bottom: 1px solid var(--gray-200);
            text-align: center;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
            animation: scaleIn 0.5s ease;
        }

        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }

        .title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .order-details {
            padding: 2rem;
        }

        .info-card {
            background: var(--gray-50);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            color: var(--gray-700);
            font-weight: 600;
        }

        .info-card-header i {
            color: var(--primary);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .detail-value {
            font-weight: 500;
            color: var(--gray-900);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .notice {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .notice-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--warning);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .notice-text {
            color: var(--gray-700);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
            transform: translateY(-2px);
        }

        @media (max-width: 640px) {
            .container {
                margin: 1rem;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="title">Pesanan Berhasil Dibuat!</h1>
            <p class="subtitle">Pesanan Anda sedang menunggu konfirmasi dari mitra laundry</p>
        </div>

        <div class="order-details">
            <div class="notice">
                <div class="notice-title">
                    <i class="fas fa-info-circle"></i>
                    <span>Informasi Penting</span>
                </div>
                <p class="notice-text">
                    Total pembayaran akan ditentukan setelah cucian ditimbang oleh mitra. 
                    Anda akan menerima notifikasi untuk melakukan pembayaran setelah penimbangan selesai.
                </p>
            </div>

            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-receipt"></i>
                    Detail Pesanan
                </div>
                <div class="detail-row">
                    <span class="detail-label">Nomor Pesanan</span>
                    <span class="detail-value"><?= $order['order_number'] ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="status-badge status-pending">
                        <i class="fas fa-clock"></i>
                        Menunggu Konfirmasi
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Mitra Laundry</span>
                    <span class="detail-value"><?= $order['laundry_name'] ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Layanan</span>
                    <span class="detail-value"><?= $order['service_name'] ?></span>
                </div>
            </div>

            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-map-marker-alt"></i>
                    Informasi Pengambilan
                </div>
                <div class="detail-row">
                    <span class="detail-label">Alamat Penjemputan</span>
                    <span class="detail-value"><?= $order['pickup_address'] ?></span>
                </div>
                <?php if ($order['notes']): ?>
                <div class="detail-row">
                    <span class="detail-label">Catatan</span>
                    <span class="detail-value"><?= $order['notes'] ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-money-bill-wave"></i>
                    Rincian Biaya
                </div>
                <div class="detail-row">
                    <span class="detail-label">Biaya Cucian</span>
                    <span class="detail-value">Menunggu Penimbangan</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Biaya Antar-Jemput</span>
                    <span class="detail-value">Rp <?= number_format($order['delivery_fee'], 0, ',', '.') ?></span>
                </div>
                <?php if ($order['discount_applied'] > 0): ?>
                <div class="detail-row">
                    <span class="detail-label">Diskon</span>
                    <span class="detail-value">- Rp <?= number_format($order['discount_applied'], 0, ',', '.') ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="actions">
                <a href="pesanan.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i>
                    Daftar Pesanan
                </a>
                <a href="home.php" class="btn btn-primary">
                    <i class="fas fa-home"></i>
                    Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
</body>
</html>