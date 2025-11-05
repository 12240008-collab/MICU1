<?php
// header.php - Header component untuk MICU Laundry
/*
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
*/

$current_page = basename($_SERVER['PHP_SELF']);
$show_full_header = ($current_page === 'home.php');
$show_minimal_header = in_array($current_page, ['pesanan.php', 'profile.php', 'tracking.php', 'payment.php', 'order.php']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Header Styles */
        .main-header {
            background: linear-gradient(135deg, #0066cc 0%, #004999 100%);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
        }

        /* Logo Section */
        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .logo-icon i {
            font-size: 24px;
            color: #0066cc;
        }

        .logo-text {
            font-size: 28px;
            font-weight: 800;
            color: white;
            letter-spacing: 1px;
        }

        /* Search Bar */
        .search-container {
            flex: 1;
            max-width: 600px;
            position: relative;
        }

        .search-wrapper {
            position: relative;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 14px 50px 14px 50px;
            border: none;
            border-radius: 25px;
            font-size: 15px;
            outline: none;
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .search-input:focus {
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 18px;
        }

        .search-clear {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 18px;
            display: none;
            transition: color 0.3s ease;
        }

        .search-clear:hover {
            color: #0066cc;
        }

        .search-input:not(:placeholder-shown) ~ .search-clear {
            display: block;
        }

        /* Search Results Dropdown */
        .search-results {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            right: 0;
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            max-height: 400px;
            overflow-y: auto;
            display: none;
            z-index: 1000;
        }

        .search-results.active {
            display: block;
        }

        .search-result-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background: #f8f9fa;
        }

        .result-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .result-icon i {
            font-size: 24px;
            color: #0066cc;
        }

        .result-info {
            flex: 1;
        }

        .result-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
            font-size: 15px;
        }

        .result-meta {
            color: #666;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .result-rating {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .result-rating i {
            color: #ffa726;
            font-size: 12px;
        }

        .search-no-results {
            padding: 40px 20px;
            text-align: center;
            color: #999;
        }

        .search-no-results i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-shrink: 0;
        }

        .header-icon {
            position: relative;
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .header-icon:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .header-icon i {
            font-size: 20px;
            color: white;
        }

        .icon-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #ff4444;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 7px;
            border-radius: 12px;
            min-width: 20px;
            text-align: center;
        }

        /* Profile Icon */
        .profile-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .profile-icon:hover {
            border-color: white;
            transform: translateY(-2px);
        }

        .profile-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .header-icon.active {
            background: rgba(255, 255, 255, 0.3);
        }

        .profile-icon.active {
            border-color: white;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .header-container {
                padding: 15px 20px;
                gap: 15px;
            }

            .search-container {
                max-width: 400px;
            }

            .logo-text {
                font-size: 24px;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                flex-wrap: wrap;
                gap: 12px;
            }

            .logo-section {
                order: 1;
            }

            .header-actions {
                order: 2;
                margin-left: auto;
            }

            .search-container {
                order: 3;
                width: 100%;
                max-width: 100%;
            }

            .logo-icon {
                width: 40px;
                height: 40px;
            }

            .logo-icon i {
                font-size: 20px;
            }

            .logo-text {
                font-size: 22px;
            }

            .header-icon {
                width: 40px;
                height: 40px;
            }

            .header-icon i {
                font-size: 18px;
            }

            .profile-icon {
                width: 40px;
                height: 40px;
            }
        }

        /* Animation */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .main-header {
            animation: fadeInDown 0.6s ease-out;
        }
    </style>
</head>
<body>

<?php if ($show_full_header): ?>
<header class="main-header">
    <div class="header-container">
        <!-- Logo -->
        <a href="home.php" class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-tint"></i>
            </div>
            <div class="logo-text">MICU</div>
        </a>

        <!-- Search Bar -->
        <div class="search-container">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input 
                    type="text" 
                    class="search-input" 
                    id="laundrySearch"
                    placeholder="Cari laundry berdasarkan nama atau lokasi..."
                    autocomplete="off"
                >
                <button class="search-clear" id="searchClear">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="search-results" id="searchResults"></div>
            </div>
        </div>

        <!-- Header Actions -->
        <div class="header-actions">
            <!-- Pesanan -->
            <a href="pesanan.php" class="header-icon" title="Pesanan Saya">
                <i class="fas fa-shopping-bag"></i>
                <?php
                // Get active orders count
                $activeOrdersCount = $conn->query("
                    SELECT COUNT(*) as count 
                    FROM orders 
                    WHERE customer_id = {$_SESSION['user_id']} 
                    AND status IN ('pending', 'waiting_payment', 'paid', 'pickup', 'processing', 'delivered')
                ")->fetch_assoc()['count'];
                
                if ($activeOrdersCount > 0):
                ?>
                <span class="icon-badge"><?php echo $activeOrdersCount; ?></span>
                <?php endif; ?>
            </a>

            <!-- Notifikasi (dari home.php, akan di-inject di sini) -->
            <div id="notificationIconPlaceholder"></div>

            <!-- Profile -->
            <a href="profile.php" class="profile-icon" title="Profil Saya">
                <?php
                $userQuery = $conn->query("SELECT profile_image, full_name FROM users WHERE id = {$_SESSION['user_id']}");
                $userData = $userQuery->fetch_assoc();
                $profileImage = !empty($userData['profile_image']) 
                    ? '../' . htmlspecialchars($userData['profile_image']) 
                    : 'https://ui-avatars.com/api/?name=' . urlencode($userData['full_name']) . '&size=45&background=0066cc&color=fff';
                ?>
                <img src="<?php echo $profileImage; ?>" alt="Profile">
            </a>
        </div>
    </div>
</header>

<script>
// Search Functionality
const searchInput = document.getElementById('laundrySearch');
const searchResults = document.getElementById('searchResults');
const searchClear = document.getElementById('searchClear');
let searchTimeout;

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();
    
    if (query.length < 2) {
        searchResults.classList.remove('active');
        return;
    }
    
    searchTimeout = setTimeout(() => {
        searchLaundry(query);
    }, 300);
});

searchClear.addEventListener('click', function() {
    searchInput.value = '';
    searchResults.classList.remove('active');
    searchInput.focus();
});

async function searchLaundry(query) {
    try {
        const response = await fetch(`includes/search_laundry.php?q=${encodeURIComponent(query)}`);
        const results = await response.json();
        
        if (results.length === 0) {
            searchResults.innerHTML = `
                <div class="search-no-results">
                    <i class="fas fa-search"></i>
                    <p>Tidak ada hasil untuk "${query}"</p>
                </div>
            `;
        } else {
            searchResults.innerHTML = results.map(laundry => `
                <div class="search-result-item" onclick="window.location.href='order.php?partner_id=${laundry.id}'">
                    <div class="result-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <div class="result-info">
                        <div class="result-name">${laundry.laundry_name}</div>
                        <div class="result-meta">
                            <div class="result-rating">
                                <i class="fas fa-star"></i>
                                <span>${parseFloat(laundry.rating).toFixed(1)}</span>
                            </div>
                            <span>•</span>
                            <span>${laundry.laundry_address}</span>
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        searchResults.classList.add('active');
    } catch (error) {
        console.error('Search error:', error);
    }
}

// Close search results when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-container')) {
        searchResults.classList.remove('active');
    }
});

// Move notification icon to header (from home.php)
document.addEventListener('DOMContentLoaded', function() {
    const notifWrapper = document.querySelector('.notification-wrapper');
    const placeholder = document.getElementById('notificationIconPlaceholder');
    
    if (notifWrapper && placeholder) {
        // Update notification wrapper styles for header
        notifWrapper.style.position = 'relative';
        notifWrapper.style.top = 'auto';
        notifWrapper.style.right = 'auto';
        
        // Move to header
        placeholder.appendChild(notifWrapper);
        
        // Update notification icon to match header style
        const notifIcon = document.getElementById('notifBtn');
        if (notifIcon) {
            notifIcon.className = 'header-icon';
            notifIcon.title = 'Notifikasi';
        }
    }
});
</script>
<?php endif; ?>

<?php if ($show_minimal_header): ?>
<header class="main-header">
    <div class="header-container">
        <!-- Logo -->
        <a href="home.php" class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-tint"></i>
            </div>
            <div class="logo-text">MICU</div>
        </a>

        <!-- Header Actions -->
        <div class="header-actions">
            <!-- Pesanan -->
            <a href="pesanan.php" class="header-icon <?php echo $current_page == 'pesanan.php' ? 'active' : ''; ?>" title="Pesanan Saya">
                <i class="fas fa-shopping-bag"></i>
                <?php
                // Get active orders count
                $activeOrdersCount = $conn->query("
                    SELECT COUNT(*) as count
                    FROM orders
                    WHERE customer_id = {$_SESSION['user_id']}
                    AND status IN ('pending', 'waiting_payment', 'paid', 'pickup', 'processing', 'delivered')
                ")->fetch_assoc()['count'];

                if ($activeOrdersCount > 0):
                ?>
                <span class="icon-badge"><?php echo $activeOrdersCount; ?></span>
                <?php endif; ?>
            </a>

            <!-- Notifikasi (dari home.php, akan di-inject di sini) -->
            <div id="notificationIconPlaceholder"></div>

            <!-- Profile -->
            <a href="profile.php" class="profile-icon <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>" title="Profil Saya">
                <?php
                $userQuery = $conn->query("SELECT profile_image, full_name FROM users WHERE id = {$_SESSION['user_id']}");
                $userData = $userQuery->fetch_assoc();
                $profileImage = !empty($userData['profile_image'])
                    ? '../' . htmlspecialchars($userData['profile_image'])
                    : 'https://ui-avatars.com/api/?name=' . urlencode($userData['full_name']) . '&size=45&background=0066cc&color=fff';
                ?>
                <img src="<?php echo $profileImage; ?>" alt="Profile">
            </a>
        </div>
    </div>
</header>

<script>
// Move notification icon to header (from home.php)
document.addEventListener('DOMContentLoaded', function() {
    const notifWrapper = document.querySelector('.notification-wrapper');
    const placeholder = document.getElementById('notificationIconPlaceholder');

    if (notifWrapper && placeholder) {
        // Update notification wrapper styles for header
        notifWrapper.style.position = 'relative';
        notifWrapper.style.top = 'auto';
        notifWrapper.style.right = 'auto';

        // Move to header
        placeholder.appendChild(notifWrapper);

        // Update notification icon to match header style
        const notifIcon = document.getElementById('notifBtn');
        if (notifIcon) {
            notifIcon.className = 'header-icon';
            notifIcon.title = 'Notifikasi';
        }
    }
});
</script>
<?php endif; ?>
