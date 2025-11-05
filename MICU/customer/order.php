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

// Ambil partner_id dari URL
$partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;

if (!$partner_id) {
    header('Location: home.php');
    exit;
}

// Ambil data laundry partner
$partnerQuery = $conn->query("
    SELECT lp.*, u.full_name as owner_name 
    FROM laundry_partners lp
    INNER JOIN users u ON lp.user_id = u.id
    WHERE lp.id = $partner_id AND lp.is_active = 1
");

if ($partnerQuery->num_rows === 0) {
    header('Location: home.php');
    exit;
}

if (!isset($conn)) {
    die("Database connection failed");
}

$partner = $partnerQuery->fetch_assoc();

$orderQuery = "
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
";

// Ambil layanan dari partner ini
$servicesQuery = $conn->query("
    SELECT * FROM services 
    WHERE partner_id = $partner_id AND is_active = 1
    ORDER BY price_per_kg ASC
");

$services = [];
while ($row = $servicesQuery->fetch_assoc()) {
    $services[] = $row;
}

// Ambil alamat customer
$addressQuery = $conn->query("
    SELECT * FROM customer_addresses 
    WHERE customer_id = $customer_id
    ORDER BY is_default DESC, created_at DESC
");

$addresses = [];
while ($row = $addressQuery->fetch_assoc()) {
    $addresses[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Laundry - <?php echo htmlspecialchars($partner['laundry_name']); ?></title>
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

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        /* Container */
        .order-container {
            max-width: 900px;
            margin: 0 auto;
            animation: fadeInUp 0.6s ease-out;
        }

        /* Header Section */
        .order-header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            animation: fadeInUp 0.6s ease-out 0.1s backwards;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            font-size: 15px;
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

        .partner-info {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .partner-logo {
            width: 80px;
            height: 80px;
            border-radius: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .partner-logo i {
            font-size: 40px;
            color: white;
        }

        .partner-details h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
        }

        .partner-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #666;
            font-size: 14px;
        }

        .meta-item i {
            color: #667eea;
        }

        .pickup-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e3f2fd;
            color: #0066cc;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        /* Form Section */
        .order-form {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            animation: fadeInUp 0.6s ease-out 0.2s backwards;
        }

        .form-section {
            margin-bottom: 35px;
            animation: slideInLeft 0.6s ease-out backwards;
        }

        .form-section:nth-child(1) { animation-delay: 0.3s; }
        .form-section:nth-child(2) { animation-delay: 0.4s; }
        .form-section:nth-child(3) { animation-delay: 0.5s; }
        .form-section:nth-child(4) { animation-delay: 0.6s; }

        .section-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #667eea;
            font-size: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            color: #555;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .required {
            color: #e74c3c;
        }

        /* Service Cards */
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .service-card {
            position: relative;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .service-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
        }

        .service-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f3ff 100%);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .service-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .service-name {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .service-description {
            font-size: 13px;
            color: #666;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .service-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .service-price {
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
        }

        .service-time {
            font-size: 12px;
            color: #999;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Input Fields */
        .form-input,
        .form-textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-input:read-only {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Pickup Toggle */
        .pickup-toggle {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .toggle-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: #333;
        }

        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 30px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        input:checked + .slider:before {
            transform: translateX(30px);
        }

        .toggle-info {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }

        .pickup-details {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            display: none;
        }

        .pickup-details.active {
            display: block;
            animation: fadeInUp 0.4s ease-out;
        }

        .info-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #856404;
            font-size: 14px;
            line-height: 1.5;
        }

        .info-box i {
            font-size: 20px;
            flex-shrink: 0;
        }

        /* Order Summary */
        .order-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 25px;
            color: white;
            margin-top: 30px;
            display: none;
        }

        .order-summary.show {
            display: block;
            animation: fadeInUp 0.4s ease-out;
        }

        .summary-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            font-weight: 700;
        }

        /* Buttons */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 18px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #666;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeInUp 0.4s ease-out;
        }

        .alert-warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
        }

        .alert i {
            font-size: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .order-header,
            .order-form {
                padding: 20px;
                border-radius: 15px;
            }

            .partner-info {
                flex-direction: column;
                text-align: center;
            }

            .partner-details h1 {
                font-size: 22px;
            }

            .service-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }
        }

        /* Loading State */
        .loading {
            pointer-events: none;
            opacity: 0.6;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 3px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Ripple Effect */
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s ease-out;
            pointer-events: none;
        }

        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <div class="order-container">
        <!-- Header -->
        <div class="order-header">
            <a href="home.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                <span>Kembali ke Beranda</span>
            </a>
            
            <div class="partner-info">
                <div class="partner-logo">
                    <i class="fas fa-tint"></i>
                </div>
                <div class="partner-details">
                    <h1><?php echo htmlspecialchars($partner['laundry_name']); ?></h1>
                    <div class="partner-meta">
                        <div class="meta-item">
                            <i class="fas fa-star"></i>
                            <span><?php echo number_format($partner['rating'], 1); ?> (<?php echo $partner['total_reviews']; ?> ulasan)</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($partner['laundry_address']); ?></span>
                        </div>
                        <?php if ($partner['has_pickup_delivery']): ?>
                        <div class="pickup-badge">
                            <i class="fas fa-truck"></i>
                            <span>Tersedia Antar Jemput</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Form -->
        <form action="process_order.php" method="POST" class="order-form" id="orderForm">
            <input type="hidden" name="partner_id" value="<?php echo $partner_id; ?>">
            <input type="hidden" name="estimated_weight" value="1">
            <input type="hidden" name="pickup_time" value="<?php echo date('Y-m-d H:i:s'); ?>">
            <!-- PROMO CODE -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-gift"></i>
                    <span>Kode Promo</span>
                </div>
                <div style="display:flex;gap:12px;align-items:center;">
                    <input type="text" id="promoCodeInput" name="promo_code" class="form-input" style="flex:1;" placeholder="Masukkan kode promo (jika ada)">
                    <button type="button" id="checkPromoBtn" class="btn btn-secondary" style="padding:12px 22px;flex-shrink:0;">Cek</button>
                </div>
                <div id="promoFeedback" style="color:#4caf50;font-size:14px;margin-top:7px;display:none;"></div>
                <input type="hidden" id="promoDiscountType" name="discount_type">
                <input type="hidden" id="promoDiscountValue" name="discount_value">
                <input type="hidden" id="promoDiscountApplied" name="discount_applied">
            </div>
            
            <?php if (count($services) === 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Maaf, saat ini mitra laundry belum memiliki layanan yang tersedia.</span>
            </div>
            <?php else: ?>

            <!-- Pilih Layanan -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-list-check"></i>
                    <span>Pilih Layanan</span>
                </div>
                <div class="service-grid">
                    <?php foreach ($services as $index => $service): ?>
                    <label class="service-card" data-service-id="<?php echo $service['id']; ?>">
                        <input type="radio" name="service_id" value="<?php echo $service['id']; ?>" 
                               data-price="<?php echo $service['price_per_kg']; ?>"
                               required <?php echo $index === 0 ? 'checked' : ''; ?>>
                        <div class="service-name"><?php echo htmlspecialchars($service['service_name']); ?></div>
                        <div class="service-description"><?php echo htmlspecialchars($service['description'] ?: 'Layanan laundry berkualitas'); ?></div>
                        <div class="service-footer">
                            <div class="service-price">Rp<?php echo number_format($service['price_per_kg'], 0, ',', '.'); ?>/kg</div>
                            <div class="service-time">
                                <i class="far fa-clock"></i>
                                <span><?php echo $service['estimated_hours']; ?> jam</span>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tanggal Pesan -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Tanggal Pesan</span>
                </div>
                <label class="form-label">Tanggal & Waktu Pemesanan</label>
                <input type="text" name="pickup_time_display" class="form-input" 
                       value="<?php echo date('d/m/Y H:i'); ?>" readonly>
                <input type="hidden" name="pickup_time" value="<?php echo date('Y-m-d H:i:s'); ?>">
            </div>

            <!-- Antar Jemput -->
            <?php if ($partner['has_pickup_delivery']): ?>
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-truck"></i>
                    <span>Layanan Antar Jemput</span>
                </div>
                <div class="pickup-toggle">
                    <div class="toggle-header">
                        <div class="toggle-label">
                            <i class="fas fa-motorcycle"></i>
                            <span>Gunakan Layanan Antar Jemput</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="pickupToggle" name="use_pickup" value="1">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-info">
                        <i class="fas fa-info-circle"></i>
                        Biaya antar jemput: <strong>Rp5.000</strong>
                    </div>
                    
                    <div class="pickup-details" id="pickupDetails">
                        <label class="form-label">Alamat Penjemputan <span class="required">*</span></label>
                        <textarea name="pickup_address_input" class="form-textarea" id="pickupAddressInput"
                                  placeholder="Masukkan alamat lengkap untuk penjemputan laundry&#10;Contoh: Jl. Merdeka No. 123, RT 01/RW 02, Kelurahan Bekasi, Kecamatan Bekasi Timur"></textarea>
                    </div>
                </div>
                
                <!-- Info jika tidak pilih antar jemput -->
                <div class="info-box" id="noPickupInfo" style="display: none;">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Mohon antar laundry Anda ke:</strong><br>
                        <?php echo htmlspecialchars($partner['laundry_address']); ?>
                    </div>
                </div>
            </div>
            
            <input type="hidden" name="pickup_address" id="finalPickupAddress" value="<?php echo htmlspecialchars($partner['laundry_address']); ?>">
            
            <?php else: ?>
            <!-- Jika tidak ada antar jemput -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-store"></i>
                    <span>Informasi Pengantaran</span>
                </div>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Mohon antar laundry Anda ke:</strong><br>
                        <?php echo htmlspecialchars($partner['laundry_address']); ?>
                    </div>
                </div>
                <input type="hidden" name="pickup_address" value="<?php echo htmlspecialchars($partner['laundry_address']); ?>">
            </div>
            <?php endif; ?>

            <!-- Catatan -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-sticky-note"></i>
                    <span>Catatan Tambahan</span>
                </div>
                <label class="form-label">Catatan (Opsional)</label>
                <textarea name="notes" class="form-textarea" 
                          placeholder="Contoh: Pisahkan pakaian putih dan berwarna, jangan gunakan pewangi terlalu banyak, dll."></textarea>
            </div>

            <!-- Order Summary (hanya muncul jika ada biaya antar jemput) -->
            <div class="order-summary" id="orderSummary">
                <div class="summary-title">Ringkasan Pembayaran</div>
                <div class="summary-row">
                    <span>Biaya Antar Jemput</span>
                    <span id="deliveryFee">Rp<?php echo number_format($partner['delivery_fee'], 0, ',', '.'); ?></span>
                </div>
                <div class="summary-row" id="promoRow" style="display:none;">
                    <span>Promo (<span id="promoRowCode"></span>)</span>
                    <span id="promoValueDisplay" style="color:#4caf50;"></span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span id="totalAmountDisplay"></span>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <a href="home.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    <span>Batal</span>
                </a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <span>Lanjutkan Pemesanan</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </div>

            <?php endif; ?>
        </form>
    </div>

    <script>
        // DOM Elements
        const orderForm = document.getElementById('orderForm');
        const serviceCards = document.querySelectorAll('.service-card');
        const pickupToggle = document.getElementById('pickupToggle');
        const pickupDetails = document.getElementById('pickupDetails');
        const pickupAddressInput = document.getElementById('pickupAddressInput');
        const finalPickupAddress = document.getElementById('finalPickupAddress');
        const orderSummary = document.getElementById('orderSummary');
        const noPickupInfo = document.getElementById('noPickupInfo');
        const submitBtn = document.getElementById('submitBtn');

        const hasPickupDelivery = <?php echo $partner['has_pickup_delivery'] ? 'true' : 'false'; ?>;
        const laundryAddress = "<?php echo htmlspecialchars($partner['laundry_address']); ?>";

        // Initialize - Set first service as selected
        document.addEventListener('DOMContentLoaded', function() {
            const firstService = document.querySelector('.service-card');
            if (firstService) {
                firstService.classList.add('selected');
            }
        });

        // Service Card Selection
        serviceCards.forEach(card => {
            card.addEventListener('click', function(e) {
                // Prevent double-trigger if clicking on radio directly
                if (e.target.tagName === 'INPUT') return;
                
                const radio = this.querySelector('input[type="radio"]');
                
                // Remove selected from all
                serviceCards.forEach(c => c.classList.remove('selected'));
                
                // Add selected to this card
                this.classList.add('selected');
                
                // Check the radio
                radio.checked = true;
            });
        });

        // Pickup Toggle Handler
        if (pickupToggle) {
            pickupToggle.addEventListener('change', function() {
                if (this.checked) {
                    // Pilih antar jemput
                    pickupDetails.classList.add('active');
                    orderSummary.classList.add('show');
                    if (noPickupInfo) noPickupInfo.style.display = 'none';
                    
                    // Set required
                    if (pickupAddressInput) {
                        pickupAddressInput.required = true;
                    }
                } else {
                    // Tidak pilih antar jemput
                    pickupDetails.classList.remove('active');
                    orderSummary.classList.remove('show');
                    if (noPickupInfo) noPickupInfo.style.display = 'flex';
                    
                    // Remove required
                    if (pickupAddressInput) {
                        pickupAddressInput.required = false;
                        pickupAddressInput.value = '';
                    }
                    
                    // Set alamat ke laundry
                    finalPickupAddress.value = laundryAddress;
                }
            });
        }

        // Form Submit Handler
        orderForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Validate service selection
            const selectedService = document.querySelector('input[name="service_id"]:checked');
            if (!selectedService) {
                showAlert('Silakan pilih layanan terlebih dahulu!', 'warning');
                return;
            }

            // Validate pickup address if toggle is on
            if (hasPickupDelivery && pickupToggle && pickupToggle.checked) {
                const addressValue = pickupAddressInput.value.trim();
                
                if (!addressValue) {
                    showAlert('Silakan masukkan alamat penjemputan!', 'warning');
                    pickupAddressInput.focus();
                    return;
                }
                
                // Set final address
                finalPickupAddress.value = addressValue;
            } else {
                // Jika tidak pakai antar jemput, set ke alamat laundry
                finalPickupAddress.value = laundryAddress;
            }

            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;

            // Submit form
            setTimeout(() => {
                this.submit();
            }, 500);
        });

        // Show Alert Function
        function showAlert(message, type = 'warning') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <span>${message}</span>
            `;

            const formSection = document.querySelector('.form-section');
            formSection.parentNode.insertBefore(alertDiv, formSection);

            // Auto remove after 5 seconds
            setTimeout(() => {
                alertDiv.style.animation = 'fadeInUp 0.4s ease-out reverse';
                setTimeout(() => alertDiv.remove(), 400);
            }, 5000);

            // Scroll to alert
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Add ripple effect to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;

                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');

                this.appendChild(ripple);

                setTimeout(() => ripple.remove(), 600);
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

const partnerDeliveryFee = <?php echo (float)$partner['delivery_fee']; ?>;
    let baseSubtotal = 0;
    let currentPromo = null;
    const deliveryFee = partnerDeliveryFee;

    function recalcOrderSummary() {
        const weight = parseFloat(document.getElementById('estimatedWeight')?.value || 1);
        const svcRadio = document.querySelector('input[name="service_id"]:checked');
        const svcPrice = parseFloat(svcRadio?.getAttribute('data-price') || 0);
        baseSubtotal = weight * svcPrice;

        let applied = 0;
        if (currentPromo && baseSubtotal >= (currentPromo.min_order || 0)) {
            if (currentPromo.discount_type === 'amount') {
                applied = Math.min(parseFloat(currentPromo.discount_value), baseSubtotal);
            } else if (currentPromo.discount_type === 'percent') {
                applied = baseSubtotal * (parseFloat(currentPromo.discount_value) / 100);
            }
        }

        const total = baseSubtotal + (pickupToggle?.checked ? deliveryFee : 0) - applied;

        // Update UI
        document.getElementById('deliveryFee').textContent = 
            pickupToggle?.checked ? 'Rp' + deliveryFee.toLocaleString('id-ID') : 'Rp0';

        if (currentPromo && applied > 0) {
            document.getElementById('promoRow').style.display = 'flex';
            document.getElementById('promoRowCode').textContent = currentPromo.code;
            document.getElementById('promoValueDisplay').textContent = '-Rp' + applied.toLocaleString('id-ID');
        } else {
            document.getElementById('promoRow').style.display = 'none';
        }

        document.getElementById('totalAmountDisplay').textContent = 
            'Rp' + Math.max(0, total).toLocaleString('id-ID');

        // Update hidden fields
        document.getElementById('promoDiscountApplied').value = Math.floor(applied);
        document.getElementById('promoDiscountType').value = currentPromo?.discount_type || '';
        document.getElementById('promoDiscountValue').value = currentPromo?.discount_value || '';
    }

    // Event Listeners
    document.querySelectorAll('input[name="service_id"]').forEach(el => {
        el.addEventListener('change', recalcOrderSummary);
    });

    const weightInput = document.getElementById('estimatedWeight');
    if (weightInput) weightInput.addEventListener('input', recalcOrderSummary);

    if (pickupToggle) pickupToggle.addEventListener('change', recalcOrderSummary);

    // Cek Promo
    document.getElementById('checkPromoBtn').onclick = async function() {
        const code = document.getElementById('promoCodeInput').value.trim();
        if (!code) return;

        const res = await fetch(`includes/check_promo.php?partner_id=<?php echo $partner_id; ?>&code=${encodeURIComponent(code)}&subtotal=${baseSubtotal}`);
        const out = await res.json();
        const fb = document.getElementById('promoFeedback');

        if (out.valid) {
            fb.style.display = 'block';
            fb.style.color = '#4caf50';
            fb.innerHTML = `<i class="fas fa-check-circle"></i> Promo valid: <strong>${out.code}</strong> 
                (${out.discount_type === 'percent' ? out.discount_value + '%' : 'Rp' + parseInt(out.discount_value).toLocaleString('id-ID')})`;

            currentPromo = out;
        } else {
            fb.style.display = 'block';
            fb.style.color = '#e74c3c';
            fb.innerHTML = `<i class="fas fa-times-circle"></i> ${out.error}`;
            currentPromo = null;
        }
        recalcOrderSummary();
    };

    // Reset promo saat input berubah
    document.getElementById('promoCodeInput').oninput = function() {
        document.getElementById('promoFeedback').style.display = 'none';
        currentPromo = null;
        recalcOrderSummary();
    };

    // Init
    window.addEventListener('DOMContentLoaded', recalcOrderSummary);

        // Pastikan hanya SATU hidden input pickup_address
        document.addEventListener("DOMContentLoaded", function() {
          // Update value hidden setiap mode antar-jemput berubah
          const pickupToggle = document.getElementById('pickupToggle');
          const laundryAddress = "<?php echo htmlspecialchars($partner['laundry_address']); ?>";
          const finalPickupAddress = document.getElementById('finalPickupAddress');
          const pickupAddressInput = document.getElementById('pickupAddressInput');

          if (pickupToggle && finalPickupAddress) {
            pickupToggle.addEventListener('change', function(){
              if (pickupToggle.checked && pickupAddressInput) {
                // mode antar jemput aktif, pakai alamat input
                finalPickupAddress.value = pickupAddressInput.value.trim();
              } else {
                // mode antar jemput off, pakai alamat laundry
                finalPickupAddress.value = laundryAddress;
              }
            });
            // Update value juga saat isi pickupAddressInput berubah
            if (pickupAddressInput) {
              pickupAddressInput.addEventListener('input', function() {
                if (pickupToggle.checked) {
                  finalPickupAddress.value = pickupAddressInput.value.trim();
                }
              });
            }
          }
        });

        
    </script>
</body>
</html>