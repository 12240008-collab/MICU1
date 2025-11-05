<?php
session_start();
require_once '../config.php';
require_once '../helpers.php';

// Mock calculateDistance for testing
if (!function_exists('calculateDistance')) {
    function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return round($earth_radius * $c, 2);
    }
}

/*
requireCustomer();
$user = getCurrentUser();
$user_id = intval($user['id']);
*/

// Mock user for testing
$user = ['id' => 1, 'full_name' => 'Test User'];
$user_id = 1;

include 'includes/header.php';
?>

<!-- Notifikasi -->
<?php
// NOTIFIKASI
$notif_count = 0;
$recent_notifications = [];
if ($conn) {
    $notifQ = $conn->query("
        SELECT 
            ot.id as tracking_id,
            ot.order_id,
            ot.status,
            ot.notes,
            ot.created_at,
            o.order_number,
            lp.laundry_name
        FROM order_tracking ot
        JOIN orders o ON ot.order_id = o.id
        JOIN laundry_partners lp ON o.partner_id = lp.id
        INNER JOIN (
            SELECT order_id, MAX(created_at) as max_date
            FROM order_tracking
            WHERE notified = 0
            GROUP BY order_id
        ) latest ON ot.order_id = latest.order_id AND ot.created_at = latest.max_date
        WHERE o.customer_id = {$user['id']}
          AND ot.notified = 0
          AND o.hidden_by_customer = 0
        ORDER BY ot.created_at DESC
        LIMIT 5
    ");
    if ($notifQ) {
        $recent_notifications = $notifQ->fetch_all(MYSQLI_ASSOC);
        $notif_count = count($recent_notifications);
    }
}
?>
<div class="notification-wrapper">
    <div class="notification-icon" id="notifBtn">
        <i class="fas fa-bell"></i>
        <?php if ($notif_count > 0): ?>
        <span class="notif-badge" id="notifCount"><?php echo $notif_count; ?></span>
        <?php endif; ?>
    </div>

    <div class="notif-popup" id="notifPopup">
        <div class="notif-header">
            <h4>Notifikasi</h4>
            <button class="notif-clear" id="clearNotif"><i class="fas fa-trash"></i> Hapus Semua</button>
        </div>
        <div class="notif-list" id="notifList">
            <?php if (empty($recent_notifications)): ?>
                <div style="padding:2rem;text-align:center;color:#94a3b8;">
                    <i class="fas fa-bell-slash fa-3x mb-3 opacity-50"></i>
                    <p>Tidak ada notifikasi baru</p>
                </div>
            <?php else: ?>
                <?php 
                $status_messages = [
                    'pending' => 'Pesanan dibuat, menunggu konfirmasi laundry',
                    'paid' => 'Pembayaran diterima, pesanan diteruskan',
                    'processing' => 'Laundry kamu sedang diproses',
                    'pickup' => 'Laundrymu akan diambil driver',
                    'delivered' => 'Laundrymu sedang diantar',
                    'done' => 'Laundrymu sudah selesai!',
                    'cancelled' => 'Pesanan dibatalkan'
                ];
                $status_icons = [
                    'pending' => 'fa-tshirt', 
                    'paid' => 'fa-money-bill', 
                    'processing' => 'fa-sync',
                    'pickup' => 'fa-truck', 
                    'delivered' => 'fa-shipping-fast', 
                    'done' => 'fa-check-circle',
                    'cancelled' => 'fa-times-circle'
                ];
                foreach ($recent_notifications as $notif): 
                    $msg = $status_messages[$notif['status']] ?? 'Update pesanan';
                    $icon = $status_icons[$notif['status']] ?? 'fa-info-circle';
                ?>
                <div class="notif-item new">
                    <i class="fas <?php echo $icon; ?> notif-icon"></i>
                    <div class="notif-info">
                        <p><strong><?php echo htmlspecialchars($msg); ?></strong> dari <b><?php echo htmlspecialchars($notif['laundry_name']); ?></b></p>
                        <span class="notif-time"><?php echo date('d M H:i', strtotime($notif['created_at'])); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MICU - Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php
    // === LAUNDRY PARTNERS QUERY ===
    $filter_sort = $_GET['sort'] ?? 'rating';
    $filter_service = $_GET['service'] ?? 'all';
    $filter_rating = floatval($_GET['rating'] ?? 0);
    $params = [];
    $query = "
        SELECT lp.*, u.full_name as owner_name, COUNT(DISTINCT s.id) as total_services
        FROM laundry_partners lp
        INNER JOIN users u ON lp.user_id = u.id
        LEFT JOIN services s ON lp.id = s.partner_id AND s.is_active = 1
        WHERE lp.is_active = 1
    ";
    if ($filter_service !== 'all') {
        $query .= " AND s.service_type = ? ";
        $params[] = $filter_service;
    }
    if ($filter_rating > 0) {
        $query .= " AND lp.rating >= ? ";
        $params[] = $filter_rating;
    }
    $query .= " GROUP BY lp.id ";
    if ($filter_sort === 'distance') {
        // Sorting by distance will be done after fetching due to SQL limitations
        $orderBy = '';
    } elseif ($filter_sort === 'reviews') {
        $orderBy = 'ORDER BY lp.total_reviews DESC, lp.rating DESC';
    } else {
        $orderBy = 'ORDER BY lp.rating DESC, lp.total_reviews DESC';
    }
    $query .= $orderBy;
    $stmt = $conn->prepare($query . '');
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $laundries = $result->fetch_all(MYSQLI_ASSOC);
    // Fake customer location, should fetch from session or JS geo
    $customer_lat = -6.2383; 
    $customer_lon = 106.9756;
    // If sorting by distance, sort the result here
    if ($filter_sort === 'distance') {
        usort($laundries, function($a, $b) use ($customer_lat, $customer_lon) {
            global $conn; // Not needed but left for clarity
            $distA = calculateDistance($customer_lat, $customer_lon, $a['latitude'], $a['longitude']);
            $distB = calculateDistance($customer_lat, $customer_lon, $b['latitude'], $b['longitude']);
            return $distA <=> $distB;
        });
    }
    ?>
    
    <style>

        /* ========== NOTIFIKASI ========== */
.notification-wrapper {
    position: absolute;
    top: 25px;
    right: 40px;
    display: inline-block;
    z-index: 200;
}

.notification-icon {
    position: relative;
    font-size: 22px;
    color: #ffffff;
    cursor: pointer;
    transition: all 0.3s ease;
}

.notification-icon:hover {
    color: #d9eaff;
    transform: scale(1.05);
}

.notif-badge {
    position: absolute;
    top: -6px;
    right: -8px;
    background: #ff4444;
    color: white;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 50%;
}

.notif-popup {
    position: absolute;
    right: 0;
    top: 40px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    width: 320px;
    display: none;
    flex-direction: column;
    animation: fadeInUp 0.3s ease;
}

.notif-popup.active {
    display: flex;
}

.notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
}

.notif-header h4 {
    font-size: 15px;
    color: #333;
}

.notif-clear {
    background: none;
    border: none;
    color: #888;
    font-size: 13px;
    cursor: pointer;
    transition: color 0.3s;
}

.notif-clear:hover {
    color: #0066cc;
}

.notif-list {
    max-height: 300px;
    overflow-y: auto;
}

.notif-item {
    display: flex;
    align-items: start;
    gap: 10px;
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}

.notif-item:hover {
    background: #f8fafc;
}

.notif-item.new {
    background: #e8f3ff;
}

.notif-icon {
    font-size: 20px;
    color: #0066cc;
    flex-shrink: 0;
}

.notif-icon.done {
    color: #2ecc71;
}

.notif-icon.paid {
    color: #27ae60;
}

.notif-info p {
    font-size: 14px;
    color: #333;
    margin: 0;
}

.notif-time {
    font-size: 12px;
    color: #888;
}


        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            overflow-x: hidden;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideInLeft {
            from {
                transform: translateX(-30px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #0066cc 0%, #004999 100%);
            padding: 60px 40px 80px;
            color: white;
            animation: fadeIn 0.8s ease-out;
        }

        .hero-container {
            max-width: 1400px;
            margin: 0 auto;
            text-align: center;
        }

        .hero-title {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 15px;
            animation: fadeInUp 0.8s ease-out 0.2s backwards;
        }

        .hero-subtitle {
            font-size: 18px;
            opacity: 0.95;
            margin-bottom: 30px;
            animation: fadeInUp 0.8s ease-out 0.3s backwards;
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 50px;
            margin-top: 40px;
            flex-wrap: wrap;
            animation: fadeInUp 0.8s ease-out 0.4s backwards;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            display: block;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }

        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: -40px auto 60px;
            padding: 0 40px;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            animation: fadeInUp 0.8s ease-out 0.5s backwards;
        }

        .filter-item {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
            display: block;
            font-weight: 500;
        }

        .filter-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-select:focus {
            border-color: #0066cc;
        }

        /* Section Header */
        .section-header {
            margin-bottom: 30px;
            animation: slideInLeft 0.8s ease-out 0.6s backwards;
        }

        .section-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .section-subtitle {
            color: #666;
            font-size: 15px;
        }

        /* Laundry Grid */
        .laundry-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .laundry-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease-out backwards;
            cursor: pointer;
        }

        .laundry-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0, 102, 204, 0.15);
        }

        .laundry-card:nth-child(1) { animation-delay: 0.7s; }
        .laundry-card:nth-child(2) { animation-delay: 0.8s; }
        .laundry-card:nth-child(3) { animation-delay: 0.9s; }
        .laundry-card:nth-child(4) { animation-delay: 1s; }
        .laundry-card:nth-child(5) { animation-delay: 1.1s; }
        .laundry-card:nth-child(6) { animation-delay: 1.2s; }

        .laundry-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .laundry-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .laundry-image i {
            font-size: 60px;
            color: #0066cc;
            opacity: 0.3;
        }

        .laundry-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.95);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: #0066cc;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .laundry-content {
            padding: 20px;
        }

        .laundry-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .laundry-name {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .laundry-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            background: #fff3e0;
            padding: 5px 10px;
            border-radius: 8px;
            flex-shrink: 0;
        }

        .laundry-rating i {
            color: #ffa726;
            font-size: 14px;
        }

        .rating-number {
            font-weight: 700;
            color: #333;
            font-size: 14px;
        }

        .rating-count {
            color: #666;
            font-size: 12px;
        }

        .laundry-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }

        .info-item i {
            color: #0066cc;
            width: 18px;
            text-align: center;
        }

        .laundry-description {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .laundry-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .service-count {
            font-size: 13px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .service-count i {
            color: #0066cc;
        }

        .btn-order {
            background: linear-gradient(135deg, #0066cc 0%, #004999 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-order:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 102, 204, 0.3);
        }

        .btn-order i {
            font-size: 14px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            animation: fadeInUp 0.8s ease-out;
        }

        .empty-state i {
            font-size: 80px;
            color: #e0e0e0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #666;
            font-size: 15px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 40px 20px 60px;
            }

            .hero-title {
                font-size: 32px;
            }

            .hero-subtitle {
                font-size: 16px;
            }

            .hero-stats {
                gap: 30px;
            }

            .stat-number {
                font-size: 28px;
            }

            .main-content {
                padding: 0 20px;
                margin-top: -30px;
            }

            .filter-section {
                padding: 20px;
            }

            .laundry-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .section-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-container">
            <h1 class="hero-title">Temukan Laundry Terbaik di Sekitar Anda</h1>
            <p class="hero-subtitle">Pesan layanan laundry dengan mudah, cepat, dan terpercaya</p>
            
            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($laundries); ?>+</span>
                    <span class="stat-label">Mitra Laundry</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">1000+</span>
                    <span class="stat-label">Pelanggan Puas</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">24/7</span>
                    <span class="stat-label">Layanan Tersedia</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-item">
                <label class="filter-label">Urutkan Berdasarkan</label>
                <select class="filter-select" id="sortBy">
                    <option value="rating">Rating Tertinggi</option>
                    <option value="distance">Terdekat</option>
                    <option value="reviews">Paling Populer</option>
                </select>
            </div>
            <div class="filter-item">
                <label class="filter-label">Layanan</label>
                <select class="filter-select" id="serviceFilter">
                    <option value="all">Semua Layanan</option>
                    <option value="pickup">Antar Jemput</option>
                    <option value="express">Express</option>
                </select>
            </div>
            <div class="filter-item">
                <label class="filter-label">Rating Minimum</label>
                <select class="filter-select" id="ratingFilter">
                    <option value="0">Semua Rating</option>
                    <option value="4">4+ Bintang</option>
                    <option value="4.5">4.5+ Bintang</option>
                </select>
            </div>
        </div>

        <!-- Section Header -->
        <div class="section-header">
            <h2 class="section-title">Laundry Partner Terpercaya</h2>
            <p class="section-subtitle">Pilih dari <?php echo count($laundries); ?> mitra laundry terbaik kami</p>
        </div>

        <!-- Laundry Grid -->
        <?php if (count($laundries) > 0): ?>
        <div class="laundry-grid">
            <?php foreach ($laundries as $laundry): 
                $distance = calculateDistance($customer_lat, $customer_lon, $laundry['latitude'], $laundry['longitude']);
            ?>
            <div class="laundry-card">
                <div class="laundry-image">
                    <?php if (!empty($laundry['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($laundry['image_url']); ?>" alt="<?php echo htmlspecialchars($laundry['laundry_name']); ?>">
                    <?php else: ?>
                        <i class="fas fa-tint"></i>
                    <?php endif; ?>
                    
                    <?php if ($laundry['has_pickup_delivery']): ?>
                    <div class="laundry-badge">
                        <i class="fas fa-truck"></i>
                        Antar Jemput
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="laundry-content">
                    <div class="laundry-header">
                        <div>
                            <h3 class="laundry-name"><?php echo htmlspecialchars($laundry['laundry_name']); ?></h3>
                        </div>
                        <div class="laundry-rating">
                            <i class="fas fa-star"></i>
                            <span class="rating-number"><?php echo number_format($laundry['rating'], 1); ?></span>
                            <span class="rating-count">(<?php echo $laundry['total_reviews']; ?>)</span>
                        </div>
                    </div>
                    
                    <div class="laundry-info">
                        <div class="info-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($laundry['laundry_address']); ?></span>
                        </div>
                        <?php if ($distance !== null): ?>
                        <div class="info-item">
                            <i class="fas fa-route"></i>
                            <span><?php echo $distance; ?> km dari lokasi Anda</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="laundry-footer">
                        <div class="service-count">
                            <i class="fas fa-list"></i>
                            <span><?php echo $laundry['total_services']; ?> Layanan Tersedia</span>
                        </div>
                        <a href="order.php?partner_id=<?php echo $laundry['id']; ?>" class="btn-order">
                            <span>Pesan Sekarang</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-store-slash"></i>
            <h3>Belum Ada Laundry Partner</h3>
            <p>Saat ini belum ada mitra laundry yang tersedia. Silakan coba lagi nanti.</p>
        </div>
        <?php endif; ?>
    </main>

    <script>
    // Filter Section Logic (Reload page on change with selected filters)
    ["sortBy", "serviceFilter", "ratingFilter"].forEach(id => {
        document.getElementById(id).addEventListener('change', function () {
            const sort = document.getElementById('sortBy').value;
            const service = document.getElementById('serviceFilter').value;
            const rating = document.getElementById('ratingFilter').value;
            const qs = `?sort=${sort}&service=${service}&rating=${rating}`;
            window.location.search = qs;
        });
    });
    </script>

<script>
let lastNotifIds = [];

function renderNotifications(list) {
    const notifList = document.getElementById('notifList');
    let notifCount = 0;
    if (!list || list.length === 0) {
        notifList.innerHTML = `<div style="padding:2rem;text-align:center;color:#94a3b8;"><i class=\"fas fa-bell-slash fa-3x mb-3 opacity-50\"></i><p>Tidak ada notifikasi baru</p></div>`;
        document.getElementById('notifCount')?.remove();
    } else {
        notifList.innerHTML = '';
        const statusMessages = {
            'pending': 'Pesanan dibuat, menunggu konfirmasi laundry',
            'paid': 'Pembayaran diterima, pesanan diteruskan',
            'processing': 'Laundry kamu sedang diproses',
            'pickup': 'Laundrymu akan diambil driver',
            'delivered': 'Laundrymu sedang diantar',
            'done': 'Laundrymu sudah selesai!',
            'cancelled': 'Pesanan dibatalkan'
        };
        const statusIcons = {
            'pending': 'fa-tshirt',
            'paid': 'fa-money-bill',
            'processing': 'fa-sync',
            'pickup': 'fa-truck',
            'delivered': 'fa-shipping-fast',
            'done': 'fa-check-circle',
            'cancelled': 'fa-times-circle'
        };
        list.forEach(notif => {
            notifCount++;
            let div = document.createElement('div');
            div.className = 'notif-item new';
            if (notif.source === 'order_tracking') {
                div.dataset.trackingId = notif.tracking_id;
                div.onclick = async function() {
                    await fetch('includes/mark_tracking_read.php', { method:'POST', body:'id='+notif.tracking_id, headers: { 'Content-Type':'application/x-www-form-urlencoded' } });
                    div.classList.remove('new'); updateNotifCount(-1);
                };
                div.innerHTML = `<i class=\"fas ${statusIcons[notif.status]||'fa-info-circle'} notif-icon\"></i><div class=\"notif-info\"><p><strong>${statusMessages[notif.status]||'Update pesanan'}</strong> dari <b>${notif.laundry_name}</b></p><span class=\"notif-time\">${notif.created_at_fmt}</span></div>`;
            } else { // general notification
                div.dataset.notifId = notif.id;
                div.onclick = async function() {
                    await fetch('includes/mark_general_notification_read.php', { method:'POST', body:'id='+notif.id, headers: { 'Content-Type':'application/x-www-form-urlencoded' } });
                    div.classList.remove('new'); updateNotifCount(-1);
                    if(notif.notif_link) window.location.href=notif.notif_link;
                };
                div.innerHTML = `<i class='fas fa-bell notif-icon'></i><div class='notif-info'><p><strong>${notif.notif_title}</strong></p><p class='small'>${notif.notif_message}</p><span class='notif-time'>${notif.created_at_fmt}</span></div>`;
            }
            notifList.appendChild(div);
        });
    }
    // update badge
    const badge = document.getElementById('notifCount');
    if (notifCount > 0) {
        if (badge) badge.textContent = notifCount; else {
            let span = document.createElement('span');
            span.className='notif-badge'; span.id='notifCount'; span.textContent=notifCount;
            document.getElementById('notifBtn').appendChild(span);
        }
    } else {
        badge?.remove();
    }
    // keep latest notif IDs for polling
    lastNotifIds = list.map(n => n.source+':'+(n.tracking_id||n.id));
}
// Toast/snack notification
document.body.insertAdjacentHTML('beforeend', `<div id="notifSnack" style="display:none;position:fixed;bottom:36px;right:36px;z-index:2000;background:#222;color:#fff;padding:16px 24px;border-radius:11px;box-shadow:0 5px 15px rgba(0,0,0,0.22);font-size:16px;cursor:pointer;min-width:210px;max-width:370px;"></div>`);
function showNotifSnack(text,onClick) {
    const snack = document.getElementById('notifSnack');
    snack.textContent = text; snack.style.display='block';
    function hide(){snack.style.display='none';}
    snack.onclick = function(){ hide(); if(onClick)onClick();};
    setTimeout(hide, 6000);
}
async function loadNotifications(showToast=true) {
    let res = await fetch('includes/get_notifications.php');
    let list = await res.json();
    renderNotifications(list); // always update UI
    if(showToast){
        let currentIds = list.map(n => n.source+':'+(n.tracking_id||n.id));
        if(lastNotifIds.length && currentIds[0] && currentIds[0]!==lastNotifIds[0]){
            let latest=list[0];
            let label = latest.source==="order_tracking"? (({
                'pending': 'Pesanan satu belum dikonfirmasi',
                'paid': 'Pembayaran diterima',
                'processing': 'Pesanan diproses',
                'pickup': 'Laundrymu akan diambil',
                'delivered': 'Sedang diantar',
                'done': 'Pesanan selesai',
                'cancelled': 'Pesanan dibatalkan'
            })[latest.status]||'Update Pesanan') : (latest.notif_title||'Notifikasi Baru');
            showNotifSnack(label,()=>{
                document.getElementById('notifPopup').classList.add('active');
            });
        }
        lastNotifIds = currentIds;
    }
}
setInterval(() => loadNotifications(true), 10000);
// notification popup open
const notifBtn = document.getElementById('notifBtn');
notifBtn.addEventListener('click', () => {
    document.getElementById('notifPopup').classList.toggle('active');
    loadNotifications(false);
});
document.getElementById('clearNotif').addEventListener('click', async () => {
    await fetch('includes/clear_tracking_notifications.php', { method: 'POST' });
    renderNotifications([]);
});
window.addEventListener('click', (e) => {
    if (!document.getElementById('notifPopup').contains(e.target) && !document.getElementById('notifBtn').contains(e.target)) {
        document.getElementById('notifPopup').classList.remove('active');
    }
});
function updateNotifCount(delta) {
    const badge = document.getElementById('notifCount');
    if (!badge) return;
    let count = parseInt(badge.textContent) + delta;
    if (count <= 0) badge.remove(); else badge.textContent = count;
}
</script>


</body>
</html>

<?php include 'includes/footer.php'; ?>
