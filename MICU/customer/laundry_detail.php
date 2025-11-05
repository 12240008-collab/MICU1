<?php
// File: customer/laundry_detail.php
// Halaman Detail Laundry Partner - MICU Laundry
$page_title = "Detail Laundry";
require_once '../config.php';
require_once '../helpers.php';

requireCustomer();
$user = getCurrentUser();
$partner_id = intval($_GET['id'] ?? 0);

if ($partner_id <= 0) {
    redirect('home.php');
}

// Customer location (bisa dari session/geolocation nanti)
$customer_lat = -6.2383;
$customer_lon = 106.9756;

// === LAUNDRY DETAIL ===
$stmt = $conn->prepare("
    SELECT 
        lp.*,
        u.full_name as owner_name,
        u.phone as owner_phone
    FROM laundry_partners lp
    INNER JOIN users u ON lp.user_id = u.id
    WHERE lp.id = ? AND lp.is_active = 1
");
$stmt->bind_param("i", $partner_id);
$stmt->execute();
$laundry = $stmt->get_result()->fetch_assoc();

if (!$laundry) {
    redirect('home.php');
}

$distance = calculateDistance($customer_lat, $customer_lon, $laundry['latitude'], $laundry['longitude']);

// === SERVICES ===
$services_stmt = $conn->prepare("
    SELECT * FROM services 
    WHERE partner_id = ? AND is_active = 1 
    ORDER BY price ASC
");
$services_stmt->bind_param("i", $partner_id);
$services_stmt->execute();
$services = $services_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// === REVIEWS (recent 10) ===
$reviews_stmt = $conn->prepare("
    SELECT 
        r.rating, r.comment, r.created_at,
        u.full_name, u.profile_image
    FROM reviews r
    INNER JOIN users u ON r.customer_id = u.id
    WHERE r.partner_id = ?
    ORDER BY r.created_at DESC 
    LIMIT 10
");
$reviews_stmt->bind_param("i", $partner_id);
$reviews_stmt->execute();
$reviews = $reviews_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Average rating
$avg_rating = $laundry['rating'];
$total_reviews = $laundry['total_reviews'];
?>

<?php include 'includes/header.php'; ?>

<style>
    :root {
        --primary: #0066cc; --primary-dark: #004999;
        --success: #22c55e; --warning: #f59e0b;
        --text: #1e293b; --text-light: #64748b;
        --bg: #f8fafc; --white: #ffffff;
        --shadow: 0 8px 25px rgba(0,0,0,0.1);
        --radius: 15px;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', sans-serif; background: var(--bg); }

    @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes slideIn { from { opacity: 0; transform: translateX(-30px); } to { opacity: 1; transform: translateX(0); } }

    .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; }
    
    /* Hero */
    .hero { 
        background: linear-gradient(135deg, var(--primary), var(--primary-dark)); 
        color: white; border-radius: var(--radius); 
        padding: 2rem; text-align: center; 
        margin-bottom: 2rem; 
        animation: fadeInUp 0.8s ease-out;
        position: relative; overflow: hidden;
    }
    .hero::before { 
        content: ''; position: absolute; top: 0; left: 0; 
        right: 0; bottom: 0; background: rgba(0,0,0,0.1); 
    }
    .hero-image { 
        width: 120px; height: 120px; border-radius: 50%; 
        margin: 0 auto 1rem; border: 5px solid white; 
        object-fit: cover; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .hero h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
    .hero-rating { display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin: 1rem 0; }
    .stars { color: #ffd700; font-size: 1.5rem; }
    .distance { background: rgba(255,255,255,0.2); padding: 0.5rem 1rem; border-radius: 25px; font-weight: 600; }

    /* Info Grid */
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: 2rem 0; }
    .info-card { background: white; padding: 1.5rem; border-radius: var(--radius); box-shadow: var(--shadow); animation: slideIn 0.6s ease-out; }
    .info-card i { color: var(--primary); font-size: 1.5rem; margin-bottom: 0.5rem; display: block; }
    .pickup-badge { background: var(--success); color: white; padding: 0.5rem 1rem; border-radius: 25px; font-size: 0.9rem; font-weight: 600; }

    /* Services */
    .section { margin: 3rem 0; }
    .section h2 { font-size: 2rem; margin-bottom: 1rem; color: var(--text); }
    .services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
    .service-card { 
        background: white; border-radius: var(--radius); 
        padding: 1.5rem; box-shadow: var(--shadow); 
        transition: all 0.3s; cursor: pointer;
        border-top: 4px solid var(--primary);
    }
    .service-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,102,204,0.15); }
    .service-price { font-size: 1.8rem; font-weight: 700; color: var(--primary); margin: 0.5rem 0; }

    /* Reviews */
    .reviews-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
    .review-card { 
        background: white; padding: 1.5rem; border-radius: var(--radius); 
        box-shadow: var(--shadow); position: relative;
    }
    .review-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; }
    .review-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; }

    /* CTA Button */
    .cta-fixed { 
        position: fixed; bottom: 20px; right: 20px; 
        background: linear-gradient(135deg, var(--primary), var(--primary-dark)); 
        color: white; padding: 1rem 2rem; border-radius: 50px; 
        font-size: 1.1rem; font-weight: 700; box-shadow: var(--shadow); 
        z-index: 1000; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;
        animation: pulse 2s infinite;
    }
    @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.05); } }

    @media (max-width: 768px) {
        .container { padding: 1rem; }
        .hero h1 { font-size: 2rem; }
        .info-grid { grid-template-columns: 1fr; }
        .cta-fixed { bottom: 15px; right: 15px; left: 15px; justify-content: center; }
    }
</style>

<!-- Hero Section -->
<section class="hero">
    <?php if (!empty($laundry['image_url'])): ?>
        <img src="<?php echo htmlspecialchars($laundry['image_url']); ?>" alt="<?php echo htmlspecialchars($laundry['laundry_name']); ?>" class="hero-image">
    <?php else: ?>
        <div class="hero-image" style="background: rgba(255,255,255,0.2); font-size: 3rem;">
            <i class="fas fa-tint"></i>
        </div>
    <?php endif; ?>
    
    <h1><?php echo htmlspecialchars($laundry['laundry_name']); ?></h1>
    <p style="font-size: 1.2rem; opacity: 0.9;"><?php echo htmlspecialchars($laundry['laundry_address']); ?></p>
    
    <div class="hero-rating">
        <div class="stars">
            <?php for($i=1; $i<=5; $i++): ?>
                <i class="fas fa-star <?php echo $i <= $avg_rating ? 'filled' : ''; ?>"></i>
            <?php endfor; ?>
        </div>
        <span><?php echo number_format($avg_rating, 1); ?> (<?php echo $total_reviews; ?> ulasan)</span>
    </div>
    
    <?php if ($distance !== null): ?>
        <div class="distance">
            <i class="fas fa-route"></i> <?php echo $distance; ?> km dari lokasi Anda
        </div>
    <?php endif; ?>
</section>

<div class="container">
    <!-- Info Grid -->
    <div class="info-grid">
        <?php if ($laundry['has_pickup_delivery']): ?>
            <div class="info-card">
                <i class="fas fa-truck"></i>
                <h3>Antar Jemput Gratis</h3>
                <p>Driver akan menjemput dan mengantar laundry Anda</p>
                <div class="pickup-badge">🚚 Tersedia</div>
            </div>
        <?php endif; ?>
        
        <div class="info-card">
            <i class="fas fa-clock"></i>
            <h3>Estimasi Cepat</h3>
            <p>Layanan express tersedia untuk kebutuhan mendadak</p>
        </div>
        
        <div class="info-card">
            <i class="fas fa-shield-alt"></i>
            <h3>Terpercaya</h3>
            <p>Rating <?php echo number_format($avg_rating, 1); ?> dari <?php echo $total_reviews; ?> pelanggan</p>
        </div>
        
        <div class="info-card">
            <i class="fas fa-credit-card"></i>
            <h3>Pembayaran Mudah</h3>
            <p><?php echo ucfirst($laundry['payment_type']); ?> - Bayar setelah selesai</p>
        </div>
    </div>

    <!-- Layanan Section -->
    <section class="section">
        <h2><i class="fas fa-list"></i> Layanan Tersedia (<?php echo count($services); ?>)</h2>
        <?php if (count($services) > 0): ?>
            <div class="services-grid">
                <?php foreach ($services as $service): ?>
                    <div class="service-card">
                        <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                        <div class="service-price">Rp <?php echo number_format($service['price']); ?></div>
                        <p><?php echo $service['description'] ?? 'Layanan berkualitas tinggi'; ?></p>
                        <div style="margin-top: 1rem; color: var(--text-light); font-size: 0.9rem;">
                            ⏱️ Estimasi: <?php echo $service['duration'] ?? '1-2 hari' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; background: white; border-radius: var(--radius);">
                <i class="fas fa-list-ul fa-3x" style="color: #e2e8f0; margin-bottom: 1rem;"></i>
                <h3>Belum ada layanan</h3>
                <p>Laundry ini akan segera menambahkan layanan</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- Review Section -->
    <section class="section">
        <h2><i class="fas fa-star"></i> Ulasan Pelanggan (<?php echo $total_reviews; ?>)</h2>
        <?php if (count($reviews) > 0): ?>
            <div class="reviews-grid">
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="review-avatar">
                                <?php if ($review['profile_image']): ?>
                                    <img src="../<?php echo htmlspecialchars($review['profile_image']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($review['full_name']); ?></strong>
                                <div class="stars" style="font-size: 1rem; margin-top: 0.25rem;">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <p style="color: var(--text); line-height: 1.6;"><?php echo htmlspecialchars($review['comment']); ?></p>
                        <small style="color: var(--text-light);"><?php echo date('d M Y', strtotime($review['created_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; margin-top: 2rem;">
                <a href="#" style="color: var(--primary); text-decoration: none; font-weight: 600;">Lihat semua ulasan →</a>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; background: white; border-radius: var(--radius);">
                <i class="fas fa-comment-dots fa-3x" style="color: #e2e8f0; margin-bottom: 1rem;"></i>
                <h3>Belum ada ulasan</h3>
                <p>Jadilah yang pertama memberikan ulasan!</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Fixed CTA -->
<a href="order.php?partner_id=<?php echo $partner_id; ?>" class="cta-fixed">
    <i class="fas fa-shopping-cart"></i>
    Pesan Sekarang
    <i class="fas fa-arrow-right"></i>
</a>

<script>
    // Smooth scroll & animations
    document.querySelectorAll('.service-card, .review-card').forEach((el, i) => {
        el.style.animationDelay = `${i * 0.1}s`;
    });

    // Star filled style
    document.querySelectorAll('.stars .fa-star').forEach(star => {
        if (star.classList.contains('filled')) {
            star.style.color = '#ffd700';
        }
    });
</script>

<?php include 'includes/footer.php'; ?>