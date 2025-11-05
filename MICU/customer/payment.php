<?php
session_start();
require_once '../config.php';
require_once '../helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$customer_id = $_SESSION['user_id'];
$order_id = intval($_GET['order_id'] ?? 0);

if (!$order_id) {
    header('Location: pesanan.php');
    exit;
}

// Ambil data order
$stmt = $conn->prepare("
    SELECT o.*, 
           lp.laundry_name, lp.payment_type, lp.qris_image, 
           lp.bank_name, lp.bank_account_name, lp.bank_account_number,
           lp.user_id as partner_user_id, /* Add this line */
           s.service_name
    FROM orders o
    INNER JOIN laundry_partners lp ON o.partner_id = lp.id
    INNER JOIN services s ON o.service_id = s.id
    WHERE o.id = ? AND o.customer_id = ?
");

$stmt->bind_param("ii", $order_id, $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: pesanan.php');
    exit;
}

$order = $result->fetch_assoc();
$partner = $order; // Now $partner has partner_user_id

// NEW: jika user baru saja mengupload bukti untuk order ini, arahkan ke payment_success.php
if (isset($_SESSION['payment_just_uploaded_order']) && intval($_SESSION['payment_just_uploaded_order']) === $order_id) {
    // hapus flag dan redirect
    unset($_SESSION['payment_just_uploaded_order']);
    header('Location: payment_success.php?order_id=' . $order_id);
    exit;
}

// Cek apakah sudah bisa dibayar
if (!in_array($order['status'], ['waiting_payment'])) {
    $_SESSION['error'] = "Pesanan belum bisa dibayar. Tunggu konfirmasi mitra.";
    header('Location: pesanan.php');
    exit;
}

$total = $order['total_amount'];

// Inside the POST handling section, update this part:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    
    if (!in_array($payment_method, ['qris', 'bank'])) {
        $error = "Metode pembayaran tidak valid";
    } else {
        $upload_dir = '../uploads/proof/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

        if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
            $error = "Silakan upload bukti pembayaran";
        } else {
            $file = $_FILES['proof'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (!in_array($ext, $allowed)) {
                $error = "Format harus JPG, PNG, atau WEBP";
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = "Ukuran maksimal 5MB";
            } else {
                $filename = 'proof_' . $order_id . '_' . time() . '.' . $ext;
                $path = $upload_dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $path)) {
                    $proof_path = 'uploads/proof/' . $filename;
                    $full_proof_url = $base_url . $proof_path; // Add this line to get full URL

                    $stmt = $conn->prepare("
                        UPDATE orders 
                        SET payment_method = ?, 
                            payment_proof = ?, 
                            status = 'waiting_payment'  /* Changed from 'waiting_confirmation' to 'waiting_payment' */
                        WHERE id = ?
                    ");
                    $stmt->bind_param("ssi", $payment_method, $proof_path, $order_id);
                    
                    if ($stmt->execute()) {
                        // Set session flag so when user returns to payment.php we send them to success page
                        $_SESSION['payment_just_uploaded_order'] = $order_id;

                        // Get partner's phone number using partner_user_id
                        $stmt = $conn->prepare("SELECT phone FROM users WHERE id = ?");
                        $stmt->bind_param("i", $partner['partner_user_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $partner_phone = $result->fetch_assoc()['phone'];
                        
                        // Format WhatsApp message without image link
                        $wa_msg = "Halo *{$partner['laundry_name']}*,\n\n" .
                                  "Pembayaran untuk pesanan *{$order['order_number']}* telah diupload.\n\n" .
                                  "Detail Pesanan:\n" .
                                  "- Total: " . formatRupiah($total) . "\n" .
                                  "- Metode: " . ($payment_method === 'qris' ? 'QRIS' : 'Transfer Bank') . "\n\n" .
                                  "Mohon cek bukti pembayaran dan konfirmasi di dashboard partner untuk memproses pesanan.\n" .
                                  "Terima kasih!";

                        // Create WhatsApp URL (open WhatsApp app)
                        $wa_url = "whatsapp://send?phone=" . preg_replace('/[^0-9]/', '', $partner_phone) . 
                                  "&text=" . urlencode($wa_msg) . 
                                  "&app_absent=0";

                        // Add notification
                        $notif_stmt = $conn->prepare("
                            INSERT INTO notifications (user_id, title, message, type, link)
                            VALUES (?, 'Pembayaran Masuk', ?, 'payment', ?)
                        ");
                        $notif_msg = "Bukti pembayaran telah diupload untuk pesanan #{$order['order_number']}. Menunggu konfirmasi pembayaran.";
                        $notif_link = "../partner/orders.php?order_id=$order_id";
                        $notif_stmt->bind_param("iss", $partner['partner_user_id'], $notif_msg, $notif_link);
                        $notif_stmt->execute();

                        // Open WhatsApp and let user send image from their device.
                        // Use JS redirect so session flag is set before leaving the page.
                        $safeWa = json_encode($wa_url);
                        echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width'></head><body>
                              <script>
                                // Try opening WhatsApp app; if it fails, fallback to web.whatsapp.com
                                var url = $safeWa;
                                window.location.href = url;
                                // After a short delay, also open a fallback to wa.me in case native protocol not supported
                                setTimeout(function(){
                                  var phone = " . json_encode(preg_replace('/[^0-9]/','',$partner_phone)) . ";
                                  var txt = " . json_encode($wa_msg) . ";
                                  window.location.href = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(txt);
                                }, 1200);
                              </script>
                              <noscript><a href={$safeWa}>Buka WhatsApp</a></noscript>
                              </body></html>";
                        exit;
                    } else {
                        $error = "Gagal menyimpan bukti pembayaran";
                    }
                } else {
                    $error = "Gagal upload file";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - <?= htmlspecialchars($order['order_number']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
        }

        /* Container */
        .payment-container {
            max-width: 900px;
            margin: 0 auto;
            animation: fadeInUp 0.6s ease-out;
        }

        /* Back Button */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 25px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            font-size: 15px;
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateX(-5px);
        }

        .back-button i {
            font-size: 18px;
        }

        /* Main Card */
        .payment-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        /* Header Section */
        .payment-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .payment-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .payment-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .payment-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .payment-title i {
            font-size: 36px;
        }

        .payment-subtitle {
            font-size: 16px;
            opacity: 0.95;
            margin-bottom: 30px;
        }

        /* Order Info Box */
        .order-info-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .order-number-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .order-label {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
            font-weight: 600;
        }

        .order-number {
            font-size: 24px;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
        }

        .order-divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            margin: 20px 0;
        }

        .total-amount-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-label {
            font-size: 16px;
            font-weight: 600;
            opacity: 0.9;
        }

        .total-amount {
            font-size: 36px;
            font-weight: 800;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        /* Payment Body */
        .payment-body {
            padding: 40px;
        }

        /* Alert */
        .alert {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 2px solid #f87171;
            color: #991b1b;
            padding: 18px 24px;
            border-radius: 16px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: fadeInUp 0.6s ease-out;
        }

        .alert i {
            font-size: 24px;
            flex-shrink: 0;
        }

        /* Section Title */
        .section-title {
            font-size: 20px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: #667eea;
            font-size: 24px;
        }

        /* Payment Methods */
        .payment-methods {
            display: grid;
            gap: 16px;
            margin-bottom: 40px;
        }

        .payment-method {
            border: 3px solid #e2e8f0;
            border-radius: 20px;
            padding: 0;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            background: white;
        }

        .payment-method:hover {
            border-color: #667eea;
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.2);
        }

        .payment-method.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .payment-method.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.05), transparent);
            animation: shimmer 2s linear infinite;
        }

        .payment-method input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .method-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 24px;
            position: relative;
        }

        .method-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #667eea;
            flex-shrink: 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .payment-method.active .method-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .method-info {
            flex: 1;
        }

        .method-name {
            font-size: 18px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .method-description {
            font-size: 14px;
            color: #64748b;
        }

        .method-check {
            width: 28px;
            height: 28px;
            border: 3px solid #cbd5e1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .payment-method.active .method-check {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
        }

        .method-check i {
            font-size: 14px;
            color: white;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .payment-method.active .method-check i {
            opacity: 1;
        }

        /* Method Details */
        .method-details {
            padding: 0 24px 24px;
            display: none;
        }

        .payment-method.active .method-details {
            display: block;
            animation: slideInRight 0.4s ease-out;
        }

        .qris-container {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .qris-container img {
            max-width: 280px;
            width: 100%;
            border-radius: 12px;
            border: 3px solid #e2e8f0;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .qris-instruction {
            margin-top: 16px;
            font-size: 14px;
            color: #64748b;
            font-weight: 600;
        }

        .bank-info-container {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .bank-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 2px solid #f1f5f9;
        }

        .bank-info-row:last-child {
            border-bottom: none;
        }

        .bank-info-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 600;
        }

        .bank-info-value {
            font-size: 16px;
            color: #1e293b;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }

        .copy-button {
            padding: 6px 12px;
            background: #f1f5f9;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            color: #667eea;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .copy-button:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        /* Upload Area */
        .upload-section {
            margin-bottom: 30px;
        }

        .upload-area {
            border: 3px dashed #cbd5e1;
            border-radius: 20px;
            padding: 50px 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            position: relative;
            overflow: hidden;
        }

        .upload-area::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .upload-area:hover::before {
            opacity: 1;
        }

        .upload-area:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            transform: scale(1.02);
        }

        .upload-area.dragover {
            border-color: #667eea;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            transform: scale(1.05);
        }

        .upload-area.has-file {
            border-color: #22c55e;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        }

        .upload-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .upload-icon i {
            font-size: 36px;
            color: white;
        }

        .upload-area.has-file .upload-icon {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        }

        .upload-text {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .upload-subtext {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 20px;
        }

        .file-info {
            display: none;
            margin-top: 20px;
            padding: 16px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .upload-area.has-file .file-info {
            display: block;
        }

        .file-name {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .file-name i {
            color: #22c55e;
            font-size: 18px;
        }

        .remove-file {
            margin-top: 12px;
            padding: 8px 16px;
            background: #fee2e2;
            color: #dc2626;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-file:hover {
            background: #fecaca;
        }

        #proofInput {
            display: none;
        }

        /* Submit Button */
        .submit-button {
            width: 100%;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            position: relative;
            overflow: hidden;
        }

        .submit-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .submit-button:hover::before {
            left: 100%;
        }

        .submit-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.5);
        }

        .submit-button:active {
            transform: translateY(-1px);
        }

        .submit-button i {
            font-size: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .payment-header {
                padding: 30px 20px;
            }

            .payment-title {
                font-size: 24px;
            }

            .order-number {
                font-size: 18px;
            }

            .total-amount {
                font-size: 28px;
            }

            .payment-body {
                padding: 25px 20px;
            }

            .method-header {
                padding: 20px;
            }

            .method-icon {
                width: 56px;
                height: 56px;
                font-size: 24px;
            }

            .upload-area {
                padding: 40px 20px;
            }

            .qris-container img {
                max-width: 240px;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <a href="pesanan.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            <span>Kembali ke Pesanan</span>
        </a>

        <div class="payment-card">
            <!-- Header -->
            <div class="payment-header">
                <div class="header-content">
                    <h1 class="payment-title">
                        <i class="fas fa-credit-card"></i>
                        <span>Pembayaran Pesanan</span>
                    </h1>
                    <p class="payment-subtitle">Pilih metode pembayaran dan upload bukti transfer Anda</p>

                    <div class="order-info-box">
                        <div class="order-number-row">
                            <div>
                                <div class="order-label">Nomor Pesanan</div>
                                <div class="order-number"><?= htmlspecialchars($order['order_number']) ?></div>
                            </div>
                        </div>

                        <div class="order-divider"></div>

                        <div class="total-amount-row">
                            <div class="total-label">Total Pembayaran</div>
                            <div class="total-amount"><?= formatRupiah($total) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="payment-body">
                <?php if (isset($error)): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="paymentForm">
                    <!-- Payment Methods -->
                    <div class="section-title">
                        <i class="fas fa-wallet"></i>
                        <span>Pilih Metode Pembayaran</span>
                    </div>

                    <div class="payment-methods">
                        <?php if ($partner['payment_type'] === 'qris' || !$partner['payment_type']): ?>
                        <label class="payment-method" data-method="qris">
                            <input type="radio" name="payment_method" value="qris" checked>
                            <div class="method-header">
                                <div class="method-icon">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <div class="method-info">
                                    <div class="method-name">QRIS</div>
                                    <div class="method-description">Scan dengan aplikasi pembayaran favorit Anda</div>
                                </div>
                                <div class="method-check">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                            <?php if ($partner['qris_image']): ?>
                            <div class="method-details">
                                <div class="qris-container">
                                    <img src="../<?= htmlspecialchars($partner['qris_image']) ?>" alt="QRIS Code">
                                    <p class="qris-instruction">
                                        <i class="fas fa-info-circle"></i>
                                        Scan kode QR di atas dengan aplikasi pembayaran Anda
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </label>
                        <?php endif; ?>

                        <?php if ($partner['payment_type'] === 'bank'): ?>
                        <label class="payment-method" data-method="bank">
                            <input type="radio" name="payment_method" value="bank">
                            <div class="method-header">
                                <div class="method-icon">
                                    <i class="fas fa-university"></i>
                                </div>
                                <div class="method-info">
                                    <div class="method-name">Transfer Bank</div>
                                    <div class="method-description">Transfer ke rekening mitra laundry</div>
                                </div>
                                <div class="method-check">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                            <div class="method-details">
                                <div class="bank-info-container">
                                    <div class="bank-info-row">
                                        <span class="bank-info-label">Nama Bank</span>
                                        <span class="bank-info-value"><?= htmlspecialchars($partner['bank_name']) ?></span>
                                    </div>
                                    <div class="bank-info-row">
                                        <span class="bank-info-label">Atas Nama</span>
                                        <span class="bank-info-value"><?= htmlspecialchars($partner['bank_account_name']) ?></span>
                                    </div>
                                    <div class="bank-info-row">
                                        <span class="bank-info-label">Nomor Rekening</span>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span class="bank-info-value" id="accountNumber"><?= htmlspecialchars($partner['bank_account_number']) ?></span>
                                            <button type="button" class="copy-button" onclick="copyAccountNumber()">
                                                <i class="fas fa-copy"></i>
                                                <span>Salin</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </label>
                        <?php endif; ?>
                    </div>

                    <!-- Upload Section -->
                    <div class="upload-section">
                        <div class="section-title">
                            <i class="fas fa-file-upload"></i>
                            <span>Upload Bukti Pembayaran</span>
                        </div>

                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">Klik atau Drag & Drop File</div>
                            <div class="upload-subtext">Format: JPG, PNG, atau WEBP (Maks. 5MB)</div>
                            <input type="file" name="proof" id="proofInput" accept="image/*" required>
                            
                            <div class="file-info">
                                <div class="file-name" id="fileName">
                                    <i class="fas fa-check-circle"></i>
                                    <span></span>
                                </div>
                                <button type="button" class="remove-file" onclick="removeFile()">
                                    <i class="fas fa-times"></i>
                                    Hapus File
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="submit-button">
                        <i class="fab fa-whatsapp"></i>
                        <span>Upload Bukti & Kirim ke WhatsApp</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Payment Method Selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function(e) {
                // Prevent trigger if clicking on input directly
                if (e.target.tagName === 'INPUT') return;

                // Remove active from all methods
                document.querySelectorAll('.payment-method').forEach(m => {
                    m.classList.remove('active');
                });

                // Add active to clicked method
                this.classList.add('active');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });

        // Initialize first method as active
        const firstMethod = document.querySelector('.payment-method');
        if (firstMethod) {
            firstMethod.classList.add('active');
        }

        // Upload Area Functionality
        const uploadArea = document.getElementById('uploadArea');
        const proofInput = document.getElementById('proofInput');
        const fileName = document.querySelector('#fileName span');

        // Click to upload
        uploadArea.addEventListener('click', (e) => {
            if (!e.target.closest('.remove-file')) {
                proofInput.click();
            }
        });

        // Drag & Drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            if (e.dataTransfer.files.length) {
                proofInput.files = e.dataTransfer.files;
                handleFileSelect(e.dataTransfer.files[0]);
            }
        });

        // File input change
        proofInput.addEventListener('change', (e) => {
            if (e.target.files.length) {
                handleFileSelect(e.target.files[0]);
            }
        });

        // Handle file selection
        function handleFileSelect(file) {
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

            if (!allowedTypes.includes(file.type)) {
                alert('Format file harus JPG, PNG, atau WEBP!');
                proofInput.value = '';
                return;
            }

            if (file.size > maxSize) {
                alert('Ukuran file maksimal 5MB!');
                proofInput.value = '';
                return;
            }

            uploadArea.classList.add('has-file');
            fileName.textContent = file.name;

            // Update icon
            const icon = uploadArea.querySelector('.upload-icon i');
            icon.className = 'fas fa-check-circle';

            // Update text
            uploadArea.querySelector('.upload-text').textContent = 'File Berhasil Dipilih!';
            uploadArea.querySelector('.upload-subtext').textContent = 'Klik tombol di bawah untuk mengirim';
        }

        // Remove file
        function removeFile() {
            proofInput.value = '';
            uploadArea.classList.remove('has-file');
            fileName.textContent = '';

            // Reset icon
            const icon = uploadArea.querySelector('.upload-icon i');
            icon.className = 'fas fa-cloud-upload-alt';

            // Reset text
            uploadArea.querySelector('.upload-text').textContent = 'Klik atau Drag & Drop File';
            uploadArea.querySelector('.upload-subtext').textContent = 'Format: JPG, PNG, atau WEBP (Maks. 5MB)';
        }

        // Copy account number
        function copyAccountNumber() {
            const accountNumber = document.getElementById('accountNumber').textContent;
            
            navigator.clipboard.writeText(accountNumber).then(() => {
                const button = event.target.closest('.copy-button');
                const originalHTML = button.innerHTML;
                
                button.innerHTML = '<i class="fas fa-check"></i><span>Tersalin!</span>';
                button.style.background = '#22c55e';
                button.style.color = 'white';
                button.style.borderColor = '#22c55e';
                
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.style.background = '';
                    button.style.color = '';
                    button.style.borderColor = '';
                }, 2000);
            }).catch(err => {
                alert('Gagal menyalin nomor rekening');
            });
        }

        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            if (!proofInput.files.length) {
                e.preventDefault();
                alert('Silakan upload bukti pembayaran terlebih dahulu!');
                uploadArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
                uploadArea.style.animation = 'pulse 0.5s ease-in-out 3';
                return false;
            }
        });

        // Smooth scroll for back button
        document.querySelector('.back-button').addEventListener('click', function(e) {
            e.preventDefault();
            document.body.style.animation = 'fadeInUp 0.3s ease-out reverse';
            setTimeout(() => {
                window.location.href = this.href;
            }, 300);
        });

        // Add ripple effect to buttons
        function createRipple(event) {
            const button = event.currentTarget;
            const ripple = document.createElement('span');
            const rect = button.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = event.clientX - rect.left - size / 2;
            const y = event.clientY - rect.top - size / 2;

            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.style.position = 'absolute';
            ripple.style.borderRadius = '50%';
            ripple.style.background = 'rgba(255, 255, 255, 0.6)';
            ripple.style.transform = 'scale(0)';
            ripple.style.animation = 'ripple-animation 0.6s ease-out';
            ripple.style.pointerEvents = 'none';

            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple-animation {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);

            button.style.position = 'relative';
            button.style.overflow = 'hidden';
            button.appendChild(ripple);

            setTimeout(() => ripple.remove(), 600);
        }

        document.querySelector('.submit-button').addEventListener('click', createRipple);

        // Prevent double submission
        let isSubmitting = false;
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            
            isSubmitting = true;
            const submitBtn = this.querySelector('.submit-button');
            submitBtn.style.opacity = '0.7';
            submitBtn.style.cursor = 'not-allowed';
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Mengirim...</span>';
            
            // If validation fails, reset
            if (!proofInput.files.length) {
                isSubmitting = false;
                submitBtn.style.opacity = '';
                submitBtn.style.cursor = '';
                submitBtn.innerHTML = '<i class="fab fa-whatsapp"></i><span>Upload Bukti & Kirim ke WhatsApp</span><i class="fas fa-arrow-right"></i>';
            }
        });
    </script>
</body>
</html>