<?php
ob_start();
session_start();
require_once '../config.php';
require_once '../helpers.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: ../login.php');
    exit;
}

$customer_id = $_SESSION['user_id'];

// Validasi method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    exit;
}

try {
    // Ambil data dari POST
    $partner_id = intval($_POST['partner_id'] ?? 0);
    $service_id = intval($_POST['service_id'] ?? 0);
    $estimated_weight = floatval($_POST['estimated_weight'] ?? 0);
    $use_pickup = !empty($_POST['use_pickup']);
    $pickup_address = trim($_POST['pickup_address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $promo_code = trim($_POST['promo_code'] ?? '');
    $discount_type = $_POST['discount_type'] ?? null;
    $discount_value = $_POST['discount_value'] ?? null;
    $discount_applied = floatval($_POST['discount_applied'] ?? 0);

    if (!$partner_id || !$service_id || $estimated_weight < 0.5) {
        throw new Exception("Data tidak valid");
    }

    // Ambil data partner & service
    $stmt = $conn->prepare("
        SELECT lp.*, s.price_per_kg 
        FROM laundry_partners lp
        INNER JOIN services s ON lp.id = s.partner_id
        WHERE lp.id = ? AND s.id = ? AND lp.is_active = 1 AND s.is_active = 1
    ");
    $stmt->bind_param("ii", $partner_id, $service_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Layanan tidak tersedia");
    }
    $data = $result->fetch_assoc();
    $partner = $data;
    $price_per_kg = $data['price_per_kg'];

    // Hitung subtotal
    $subtotal = $estimated_weight * $price_per_kg;
    $delivery_fee = ($partner['has_pickup_delivery'] && $use_pickup) ? $partner['delivery_fee'] : 0;

    // Validasi & simpan promo
    $final_discount = 0;
    if ($promo_code && $discount_type && $discount_value) {
        $stmt = $conn->prepare("
            SELECT * FROM partner_promotions 
            WHERE partner_id = ? AND code = ? AND is_active = 1
        ");
        $stmt->bind_param("is", $partner_id, $promo_code);
        $stmt->execute();
        $promo_result = $stmt->get_result();
        $promo = $promo_result->fetch_assoc();

        if ($promo && $subtotal >= ($promo['min_order'] ?? 0)) {
            if ($promo['discount_type'] === 'amount') {
                $final_discount = min($promo['discount_value'], $subtotal);
            } else if ($promo['discount_type'] === 'percent') {
                $final_discount = $subtotal * ($promo['discount_value'] / 100);
            }
            $final_discount = round($final_discount);

            // Update used_count
            $conn->query("UPDATE partner_promotions SET used_count = used_count + 1 WHERE id = {$promo['id']}");
        } else {
            $promo_code = $discount_type = $discount_value = null;
            $final_discount = 0;
        }
    } else {
        $promo_code = $discount_type = $discount_value = null;
        $final_discount = 0;
    }

    $total_amount = $subtotal + $delivery_fee - $final_discount;

    // Generate order number
    $order_number = 'ORD-' . date('Ymd') . '-' . rand(1000, 9999);

// Insert ke database
    $conn->begin_transaction();

$stmt = $conn->prepare("
        INSERT INTO orders (
            order_number, customer_id, partner_id, service_id, pickup_address,
            promo_code, discount_type, discount_value, discount_applied,
            estimated_weight, notes, pickup_time, status, payment_status,
            subtotal, delivery_fee, total_amount, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid', ?, ?, ?, NOW()
        )
    ");

    $pickup_time = date('Y-m-d H:i:s');

    $bind_types = "siiiss"; // 5 + promo_code
    $bind_values = [$order_number, $customer_id, $partner_id, $service_id, $pickup_address, $promo_code];

    // discount_type: string or NULL
    $bind_types .= is_null($discount_type) ? "s" : "s";
    $bind_values[] = $discount_type;

    // discount_value: decimal or NULL
    $bind_types .= is_null($discount_value) ? "s" : "d";
    $bind_values[] = $discount_value;

    // discount_applied, estimated_weight, notes, pickup_time
    $bind_types .= "ddssddd";
    $bind_values[] = $final_discount;
    $bind_values[] = $estimated_weight;
    $bind_values[] = $notes;
    $bind_values[] = $pickup_time;
    $bind_values[] = $subtotal;
    $bind_values[] = $delivery_fee;
    $bind_values[] = $total_amount;

    $stmt->bind_param($bind_types, ...$bind_values);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menyimpan pesanan: " . $stmt->error);
    }

    $order_id = $stmt->insert_id;

    // Insert tracking
    $trackStmt = $conn->prepare("
        INSERT INTO order_tracking (order_id, status, notes, created_at)
        VALUES (?, 'pending', 'Pesanan baru dibuat', NOW())
    ");
    $trackStmt->bind_param("i", $order_id);
    $trackStmt->execute();

    // Notifikasi ke customer
    $notifCustomer = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, created_at)
        VALUES (?, 'Pesanan Berhasil Dibuat', ?, 'order', ?, NOW())
    ");
    $msgCustomer = "Pesanan Anda dengan nomor $order_number telah dibuat dan menunggu konfirmasi dari mitra laundry.";
    $linkCustomer = "tracking.php?order_id=$order_id";
    $notifCustomer->bind_param("iss", $customer_id, $msgCustomer, $linkCustomer);
    $notifCustomer->execute();

    // Notifikasi ke partner
    $partner_user_id = $partner['user_id'];
    $notifPartner = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, created_at)
        VALUES (?, 'Pesanan Baru!', ?, 'order', ?, NOW())
    ");
    $msgPartner = "Ada pesanan baru dari customer (Order No: $order_number)";
    $linkPartner = "../partner/orders.php?order_id=$order_id";
    $notifPartner->bind_param("iss", $partner_user_id, $msgPartner, $linkPartner);
    $notifPartner->execute();

    $conn->commit();

    // Redirect ke halaman sukses (UI tetap sama)
    ob_end_clean();
    header("Location: process_order.php?success=1&order=$order_number&delivery=$delivery_fee&discount=$final_discount");
    exit;

} catch (Exception $e) {
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    error_log("Order Error: " . $e->getMessage());
    ob_end_clean();
    header("Location: process_order.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memproses Pesanan - MICU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* [CSS SAMA PERSIS SEPERTI YANG ANDA KIRIM — TIDAK DIUBAH SAMA SEKALI] */
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter','Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;position:relative}
        @keyframes fadeIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
        @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
        @keyframes slideInUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
        @keyframes bounce{0%,20%,50%,80%,100%{transform:translateY(0)}40%{transform:translateY(-10px)}60%{transform:translateY(-5px)}}
        @keyframes checkmark{0%{stroke-dashoffset:100}100%{stroke-dashoffset:0}}
        @keyframes scaleIn{from{transform:scale(0)}to{transform:scale(1)}}
        @keyframes shimmer{0%{background-position:-1000px 0}100%{background-position:1000px 0}}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
        .nav-buttons{position:fixed;top:20px;left:20px;display:flex;gap:12px;z-index:1000;animation:fadeIn .6s ease-out}
        .nav-btn{padding:12px 24px;background:rgba(255,255,255,.95);border:none;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;transition:all .3s ease;display:flex;align-items:center;gap:8px;text-decoration:none;color:#667eea;box-shadow:0 4px 15px rgba(0,0,0,.1);backdrop-filter:blur(10px)}
        .nav-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.15);background:white}
        .nav-btn i{font-size:16px}
        .process-container{background:white;border-radius:24px;padding:50px;max-width:650px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);animation:fadeIn .6s ease-out;text-align:center;position:relative}
        .loading-section{display:block}
        .loading-section.hidden{display:none}
        .spinner-wrapper{position:relative;width:140px;height:140px;margin:0 auto 30px}
        .spinner{width:140px;height:140px;border:8px solid #f3f4f6;border-top:8px solid #667eea;border-right:8px solid #764ba2;border-radius:50%;animation:spin 1.2s cubic-bezier(.5,0,.5,1) infinite}
        .spinner-icon{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:56px;color:#667eea}
        .loading-text{font-size:28px;font-weight:800;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:12px}
        .loading-subtext{font-size:16px;color:#64748b;line-height:1.6;margin-bottom:30px}
        .progress-bar{width:100%;height:8px;background:#e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:30px}
        .progress-fill{height:100%;background:linear-gradient(90deg,#667eea,#764ba2);border-radius:10px;transition:width .5s cubic-bezier(.4,0,.2,1);background-size:200% 100%;animation:shimmer 2s linear infinite}
        .loading-steps{text-align:left}
        .step-item{display:flex;align-items:center;gap:16px;padding:16px 24px;margin-bottom:12px;background:#f8fafc;border-radius:16px;transition:all .4s cubic-bezier(.4,0,.2,1);opacity:.4;border:2px solid transparent}
        .step-item.active{background:linear-gradient(135deg,#eff6ff 0%,#e0f2fe 100%);border-color:#667eea;opacity:1;animation:slideInUp .4s ease-out;transform:translateX(8px)}
        .step-item.completed{background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%);border-color:#22c55e;opacity:1}
        .step-icon{font-size:22px;width:48px;height:48px;display:flex;align-items:center;justify-content:center;border-radius:14px;background:white;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,.08);transition:all .3s ease}
        .step-item.active .step-icon{color:#667eea;animation:pulse 2s ease-in-out infinite}
        .step-item.completed .step-icon{color:#22c55e;background:#22c55e}
        .step-item.completed .step-icon i:before{content:"\f00c";color:white}
        .step-text{font-size:15px;color:#334155;font-weight:600;flex:1}
        .success-section{display:none}
        .success-section.show{display:block;animation:fadeIn .6s ease-out}
        .success-icon-wrapper{width:140px;height:140px;margin:0 auto 30px;position:relative}
        .success-circle{width:140px;height:140px;background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:50%;display:flex;align-items:center;justify-content:center;animation:scaleIn .6s cubic-bezier(.34,1.56,.64,1);box-shadow:0 15px 50px rgba(34,197,94,.4);position:relative}
        .success-circle::before{content:'';position:absolute;width:160px;height:160px;border:3px solid #22c55e;border-radius:50%;opacity:.3;animation:scaleIn .8s ease-out .2s backwards}
        .success-checkmark{width:110px;height:110px}
        .checkmark-circle{stroke-dasharray:166;stroke-dashoffset:166;stroke-width:3;stroke:white;fill:none;animation:checkmark .6s ease-out .3s forwards}
        .checkmark-check{stroke-dasharray:48;stroke-dashoffset:48;stroke:white;stroke-width:3;fill:none;animation:checkmark .4s ease-out .6s forwards}
        .success-title{font-size:32px;font-weight:800;background:linear-gradient(135deg,#1e293b 0%,#334155 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:12px}
        .success-message{font-size:16px;color:#64748b;margin-bottom:30px;line-height:1.8}
        .order-number-box{background:linear-gradient(135deg,#eff6ff 0%,#e0f2fe 100%);border:3px dashed #667eea;border-radius:16px;padding:20px;margin-bottom:30px;position:relative;overflow:hidden}
        .order-number-box::before{content:'';position:absolute;top:-50%;left:-50%;width:200%;height:200%;background:linear-gradient(45deg,transparent,rgba(255,255,255,.3),transparent);animation:shimmer 3s linear infinite}
        .order-number-label{font-size:13px;color:#64748b;margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
        .order-number{font-size:28px;font-weight:800;color:#667eea;font-family:'Courier New',monospace;letter-spacing:2px;position:relative}
        .important-notice{background:linear-gradient(135deg,#fff3cd 0%,#fff8e1 100%);border:2px solid #ffc107;border-radius:16px;padding:20px;margin-bottom:30px;text-align:left}
        .notice-header{display:flex;align-items:center;gap:12px;margin-bottom:12px}
        .notice-icon{width:48px;height:48px;background:#ffc107;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;color:white;animation:pulse 2s ease-in-out infinite}
        .notice-title{font-size:18px;font-weight:800;color:#856404}
        .notice-content{font-size:14px;color:#856404;line-height:1.8;padding-left:60px}
        .notice-content strong{font-weight:700;color:#664d03}
        .order-summary{background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%);border-radius:20px;padding:24px;margin-bottom:30px;text-align:left;border:2px solid #e2e8f0}
        .summary-header{font-size:18px;font-weight:800;color:#1e293b;margin-bottom:20px;display:flex;align-items:center;gap:12px}
        .summary-header i{color:#667eea;font-size:22px}
        .summary-row{display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:2px solid #e2e8f0}
        .summary-row:last-child{border-bottom:none;padding-top:18px;margin-top:10px;border-top:3px solid #cbd5e1}
        .summary-label{font-size:15px;color:#64748b;font-weight:600}
        .summary-value{font-size:15px;color:#1e293b;font-weight:700}
        .info-badge{background:#fef3c7;color:#d97706;padding:6px 12px;border-radius:20px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px}
        .action-buttons{display:flex;gap:16px;margin-top:30px;flex-wrap:wrap}
        .btn{flex:1;padding:16px 24px;border:none;border-radius:16px;font-size:16px;font-weight:700;cursor:pointer;transition:all .3s ease;display:flex;align-items:center;justify-content:center;gap:10px;text-decoration:none;min-width:160px}
        .btn-secondary{background:#e2e8f0;color:#64748b}
        .btn-secondary:hover{background:#cbd5e1;transform:translateY(-2px)}
        .btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;position:relative;overflow:hidden}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(102,126,234,.4)}
        .btn-primary::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);transition:left .5s}
        .btn-primary:hover::before{left:100%}
        .error-section{display:none}
        .error-section.show{display:block;animation:fadeIn .6s ease-out}
        .error-icon{font-size:80px;color:#ef4444;margin-bottom:20px}
        .error-title{font-size:28px;font-weight:800;color:#1e293b;margin-bottom:12px}
        .error-message{font-size:16px;color:#64748b;margin-bottom:30px;line-height:1.8}
        .error-details{background:#fee2e2;border:2px solid #fca5a5;border-radius:16px;padding:20px;margin-bottom:30px;text-align:left}
        .error-details-title{font-size:16px;font-weight:700;color:#991b1b;margin-bottom:8px;display:flex;align-items:center;gap:8px}
        .error-details-text{font-size:14px;color:#991b1b;line-height:1.6}
        @media(max-width:640px){
            .process-container{padding:30px}
            .action-buttons{flex-direction:column}
            .btn{min-width:auto}
        }
    </style>
</head>
<body>
    <div class="nav-buttons">
        <a href="home.php" class="nav-btn"><i class="fas fa-home"></i> Beranda</a>
        <a href="pesanan.php" class="nav-btn"><i class="fas fa-box"></i> Pesanan</a>
    </div>

    <div class="process-container">
        <!-- LOADING -->
        <div class="loading-section" id="loadingSection">
            <div class="spinner-wrapper">
                <div class="spinner"></div>
                <i class="fas fa-tshirt spinner-icon"></i>
            </div>
            <div class="loading-text">Memproses Pesanan...</div>
            <div class="loading-subtext">Kami sedang membuat pesanan Anda. Mohon tunggu sebentar.</div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" style="width: 0%"></div>
            </div>
            <div class="loading-steps">
                <div class="step-item" id="step1">
                    <div class="step-icon"><i class="fas fa-receipt"></i></div>
                    <div class="step-text">Membuat pesanan</div>
                </div>
                <div class="step-item" id="step2">
                    <div class="step-icon"><i class="fas fa-database"></i></div>
                    <div class="step-text">Menyimpan ke database</div>
                </div>
                <div class="step-item" id="step3">
                    <div class="step-icon"><i class="fas fa-bell"></i></div>
                    <div class="step-text">Mengirim notifikasi</div>
                </div>
                <div class="step-item" id="step4">
                    <div class="step-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="step-text">Selesai!</div>
                </div>
            </div>
        </div>

        <!-- SUCCESS -->
        <div class="success-section" id="successSection">
            <div class="success-icon-wrapper">
                <div class="success-circle">
                    <svg class="success-checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                        <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                    </svg>
                </div>
            </div>
            <h2 class="success-title">Pesanan Berhasil Dibuat!</h2>
            <p class="success-message">
                Terima kasih! Pesanan Anda telah berhasil dibuat dan sedang menunggu konfirmasi dari mitra laundry.
            </p>
            <div class="order-number-box">
                <div class="order-number-label">Nomor Pesanan Anda</div>
                <div class="order-number" id="orderNumber">ORD-20251031-1234</div>
            </div>
            <div class="important-notice">
                <div class="notice-header">
                    <div class="notice-icon"><i class="fas fa-info-circle"></i></div>
                    <div class="notice-title">Penting untuk Diperhatikan</div>
                </div>
                <div class="notice-content">
                    <strong>Harga belum final!</strong> Total pembayaran di bawah adalah <strong>perkiraan biaya antar jemput saja</strong>. Harga akhir akan ditentukan setelah mitra laundry menjemput dan <strong>menimbang cucian Anda</strong>.
                </div>
            </div>
            <div class="order-summary">
                <div class="summary-header"><i class="fas fa-file-invoice-dollar"></i> <span>Perkiraan Biaya Sementara</span></div>
                <div class="summary-row pending">
                    <span class="summary-label"><i class="fas fa-scale-balanced"></i> Biaya Cucian</span>
                    <span class="summary-value"><i class="fas fa-hourglass-half"></i> Menunggu Penimbangan</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Biaya Antar Jemput</span>
                    <span class="summary-value" id="deliveryDisplay">Rp 5.000</span>
                </div>
                <div class="summary-row" id="discountRow" style="display: none;">
                    <span class="summary-label">Diskon Promo</span>
                    <span class="summary-value" style="color: #22c55e;" id="discountDisplay">-Rp 0</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Perkiraan Total</span>
                    <span class="summary-value" id="totalDisplay">
                        <span class="info-badge"><i class="fas fa-clock"></i> Belum Final</span>
                    </span>
                </div>
            </div>
            <div class="action-buttons">
                <a href="pesanan.php" class="btn btn-secondary"><i class="fas fa-box"></i> <span>Lihat Pesanan</span></a>
                <a href="home.php" class="btn btn-primary"><i class="fas fa-home"></i> <span>Kembali ke Beranda</span></a>
            </div>
        </div>

        <!-- ERROR -->
        <div class="error-section" id="errorSection">
            <div class="error-icon"><i class="fas fa-times-circle"></i></div>
            <h2 class="error-title">Pesanan Gagal Diproses</h2>
            <p class="error-message">Maaf, terjadi kesalahan. Silakan coba lagi.</p>
            <div class="error-details">
                <div class="error-details-title"><i class="fas fa-exclamation-triangle"></i> <span>Detail Kesalahan:</span></div>
                <div class="error-details-text" id="errorMessage"></div>
            </div>
            <div class="action-buttons">
                <a href="javascript:history.back()" class="btn btn-secondary"><i class="fas fa-redo"></i> <span>Coba Lagi</span></a>
                <a href="home.php" class="btn btn-primary"><i class="fas fa-home"></i> <span>Kembali ke Beranda</span></a>
            </div>
        </div>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        const error = urlParams.get('error');

        if (success === '1') {
            const orderNumber = urlParams.get('order') || 'ORD-ERROR';
            const delivery = parseInt(urlParams.get('delivery') || 0);
            const discount = parseInt(urlParams.get('discount') || 0);

            document.getElementById('orderNumber').textContent = orderNumber;
            document.getElementById('deliveryDisplay').textContent = 'Rp ' + delivery.toLocaleString('id-ID');

            if (discount > 0) {
                document.getElementById('discountRow').style.display = 'flex';
                document.getElementById('discountDisplay').textContent = '-Rp ' + discount.toLocaleString('id-ID');
            }

            setTimeout(() => {
                document.getElementById('loadingSection').classList.add('hidden');
                document.getElementById('successSection').classList.add('show');
            }, 500);
        } else if (error) {
            document.getElementById('errorMessage').textContent = decodeURIComponent(error);
            setTimeout(() => {
                document.getElementById('loadingSection').classList.add('hidden');
                document.getElementById('errorSection').classList.add('show');
            }, 500);
        } else {
            // Animasi loading
            const steps = [
                {id:'step1',delay:500,progress:25},
                {id:'step2',delay:1000,progress:50},
                {id:'step3',delay:1500,progress:75},
                {id:'step4',delay:2000,progress:100}
            ];
            let i = 0;
            function next() {
                if (i < steps.length) {
                    setTimeout(() => {
                        if (i > 0) {
                            document.getElementById(steps[i-1].id).classList.remove('active'); 
                            document.getElementById(steps[i-1].id).classList.add('completed');
                        }
                        document.getElementById(steps[i].id).classList.add('active');
                        document.getElementById('progressFill').style.width = steps[i].progress + '%';
                        i++; next();
                    }, steps[i].delay);
                } else {
                    setTimeout(() => {
                        window.location.href = 'process_order.php?error=Simulasi%20gagal';
                    }, 500);
                }
            }
            next();
        }
    </script>
</body>
</html>