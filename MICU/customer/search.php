<?php
$page_title = "Hasil Pencarian - MICU";
require_once '../config.php';
require_once '../helpers.php';

requireCustomer();
$user = getCurrentUser();

$query = sanitize($_GET['q'] ?? '');
if (empty($query)) {
    redirect('home.php');
}

$customer_lat = -6.2383;
$customer_lon = 106.9756;

$stmt = $conn->prepare("
    SELECT 
        lp.*,
        u.full_name as owner_name,
        COUNT(DISTINCT s.id) as total_services
    FROM laundry_partners lp
    INNER JOIN users u ON lp.user_id = u.id
    LEFT JOIN services s ON lp.id = s.partner_id AND s.is_active = 1
    WHERE lp.is_active = 1 
    AND (lp.laundry_name LIKE ? OR lp.laundry_address LIKE ?)
    GROUP BY lp.id
    ORDER BY lp.rating DESC, lp.total_reviews DESC
");
$searchTerm = "%$query%";
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$laundries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<?php include 'includes/header.php'; ?>

<div style="max-width: 1400px; margin: 2rem auto; padding: 0 1.5rem;">
    <div style="background: linear-gradient(135deg, #0066cc, #004999); color: white; padding: 3rem 2rem; border-radius: 20px; text-align: center; margin-bottom: 2rem;">
        <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">
            <i class="fas fa-search"></i> Hasil Pencarian
        </h1>
        <p style="font-size: 1.2rem;">
            "<?php echo htmlspecialchars($query); ?>" 
            <strong>(<?php echo count($laundries); ?> ditemukan)</strong>
        </p>
    </div>

    <?php if (count($laundries) > 0): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem;">
            <?php foreach ($laundries as $laundry): 
                $distance = calculateDistance($customer_lat, $customer_lon, $laundry['latitude'], $laundry['longitude']);
            ?>
                <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.08); cursor: pointer;" 
                     onclick="window.location.href='laundry_detail.php?id=<?php echo $laundry['id']; ?>'">
                    <div style="height: 180px; overflow: hidden;">
                        <?php if (!empty($laundry['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($laundry['image_url']); ?>" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <div style="height:100%; background: #667eea; display:flex; align-items:center; justify-content:center; color:white; font-size:3rem;">
                                <i class="fas fa-tint"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 1.5rem;">
                        <h3 style="margin:0 0 0.5rem; font-size:1.25rem;"><?php echo htmlspecialchars($laundry['laundry_name']); ?></h3>
                        <p style="color:#64748b; margin:0 0 0.5rem; font-size:0.9rem;">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($laundry['laundry_address']); ?>
                        </p>
                        <?php if ($distance !== null): ?>
                            <p style="color:#0066cc; margin:0; font-size:0.9rem;">
                                <i class="fas fa-route"></i> <?php echo $distance; ?> km
                            </p>
                        <?php endif; ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem;">
                            <span style="color:#f59e0b;">
                                <i class="fas fa-star"></i> <?php echo number_format($laundry['rating'], 1); ?> (<?php echo $laundry['total_reviews']; ?>)
                            </span>
                            <a href="order.php?partner_id=<?php echo $laundry['id']; ?>" 
                               style="background:#0066cc; color:white; padding:0.5rem 1rem; border-radius:8px; text-decoration:none; font-size:0.9rem;"
                               onclick="event.stopPropagation();">
                               Pesan
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align:center; padding:4rem; background:white; border-radius:20px;">
            <i class="fas fa-search fa-5x" style="color:#e2e8f0; margin-bottom:1rem;"></i>
            <h3>Tidak ditemukan</h3>
            <p>Coba kata kunci lain</p>
            <a href="home.php" style="background:#0066cc; color:white; padding:0.75rem 2rem; border-radius:50px; text-decoration:none; margin-top:1rem; display:inline-block;">
                Kembali
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>