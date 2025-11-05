<?php
session_start();
require_once '../config.php';
requirePartner();

$user = getCurrentUser();
$db = db();

// Get partner info
$stmt = $db->prepare("SELECT * FROM laundry_partners WHERE user_id = ?");
$stmt->execute([$user['id']]);
$partner = $stmt->fetch();

if (!$partner) {
    redirect('partner/login.php');
}

$partner_id = $partner['id'];
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_business_info':
            $laundry_name = sanitize($_POST['laundry_name']);
            $laundry_address = sanitize($_POST['laundry_address']);
            $has_pickup = isset($_POST['has_pickup_delivery']) ? 1 : 0;
            $delivery_fee = (float)($_POST['delivery_fee'] ?? 0);
            
            if (empty($laundry_name) || empty($laundry_address)) {
                $error = 'Nama dan alamat laundry harus diisi';
            } else {
                $stmt = $db->prepare("
                    UPDATE laundry_partners 
                    SET laundry_name = ?, laundry_address = ?, has_pickup_delivery = ?, delivery_fee = ?
                    WHERE id = ?
                ");
                if ($stmt->execute([$laundry_name, $laundry_address, $has_pickup, $delivery_fee, $partner_id])) {
                    $success = 'Informasi bisnis berhasil diperbarui';
                    $partner['laundry_name'] = $laundry_name;
                    $partner['laundry_address'] = $laundry_address;
                    $partner['has_pickup_delivery'] = $has_pickup;
                    $partner['delivery_fee'] = $delivery_fee;
                } else {
                    $error = 'Gagal memperbarui informasi bisnis';
                }
            }
            break;
            
        case 'upload_laundry_image':
            if (isset($_FILES['laundry_image']) && $_FILES['laundry_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/laundry/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['laundry_image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $error = 'Format file harus JPG, PNG, atau WEBP';
                    break;
                }
                
                if ($_FILES['laundry_image']['size'] > 5 * 1024 * 1024) {
                    $error = 'Ukuran file maksimal 5MB';
                    break;
                }
                
                $new_filename = 'laundry_' . $partner_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['laundry_image']['tmp_name'], $upload_path)) {
                    // Delete old image if exists
                    if (!empty($partner['image_url']) && file_exists('../' . $partner['image_url'])) {
                        unlink('../' . $partner['image_url']);
                    }
                    
                    $image_path = 'uploads/laundry/' . $new_filename;
                    $stmt = $db->prepare("UPDATE laundry_partners SET image_url = ? WHERE id = ?");
                    if ($stmt->execute([$image_path, $partner_id])) {
                        $success = 'Gambar laundry berhasil diupload';
                        $partner['image_url'] = $image_path;
                    } else {
                        $error = 'Gagal menyimpan informasi gambar';
                    }
                } else {
                    $error = 'Gagal upload file gambar';
                }
            } else {
                $error = 'Silakan pilih file gambar terlebih dahulu';
            }
            break;
            
        case 'delete_laundry_image':
            if (!empty($partner['image_url'])) {
                if (file_exists('../' . $partner['image_url'])) {
                    unlink('../' . $partner['image_url']);
                }
                
                $stmt = $db->prepare("UPDATE laundry_partners SET image_url = NULL WHERE id = ?");
                if ($stmt->execute([$partner_id])) {
                    $success = 'Gambar laundry berhasil dihapus';
                    $partner['image_url'] = null;
                } else {
                    $error = 'Gagal menghapus informasi gambar';
                }
            }
            break;
            
        case 'update_payment_info':
            $payment_type = in_array($_POST['payment_type'], ['qris', 'bank']) ? $_POST['payment_type'] : 'qris';
            
            if ($payment_type === 'bank') {
                $bank_name = sanitize($_POST['bank_name']);
                $bank_account_name = sanitize($_POST['bank_account_name']);
                $bank_account_number = sanitize($_POST['bank_account_number']);
                
                if (empty($bank_name) || empty($bank_account_name) || empty($bank_account_number)) {
                    $error = 'Semua data bank harus diisi';
                    break;
                }
                
                $stmt = $db->prepare("
                    UPDATE laundry_partners 
                    SET payment_type = ?, bank_name = ?, bank_account_name = ?, bank_account_number = ?, qris_image = NULL
                    WHERE id = ?
                ");
                if ($stmt->execute([$payment_type, $bank_name, $bank_account_name, $bank_account_number, $partner_id])) {
                    $success = 'Informasi pembayaran berhasil diperbarui';
                    $partner['payment_type'] = $payment_type;
                    $partner['bank_name'] = $bank_name;
                    $partner['bank_account_name'] = $bank_account_name;
                    $partner['bank_account_number'] = $bank_account_number;
                } else {
                    $error = 'Gagal memperbarui informasi pembayaran';
                }
            } else {
                if (isset($_FILES['qris_image']) && $_FILES['qris_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/qris/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['qris_image']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png'];
                    
                    if (!in_array($file_extension, $allowed_extensions)) {
                        $error = 'Format file harus JPG atau PNG';
                        break;
                    }
                    
                    if ($_FILES['qris_image']['size'] > 2 * 1024 * 1024) {
                        $error = 'Ukuran file maksimal 2MB';
                        break;
                    }
                    
                    $new_filename = 'qris_' . $partner_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['qris_image']['tmp_name'], $upload_path)) {
                        if (!empty($partner['qris_image']) && file_exists('../' . $partner['qris_image'])) {
                            unlink('../' . $partner['qris_image']);
                        }
                        
                        $qris_path = 'uploads/qris/' . $new_filename;
                        $stmt = $db->prepare("
                            UPDATE laundry_partners 
                            SET payment_type = ?, qris_image = ?, bank_name = NULL, bank_account_name = NULL, bank_account_number = NULL
                            WHERE id = ?
                        ");
                        if ($stmt->execute([$payment_type, $qris_path, $partner_id])) {
                            $success = 'QRIS berhasil diperbarui';
                            $partner['payment_type'] = $payment_type;
                            $partner['qris_image'] = $qris_path;
                        } else {
                            $error = 'Gagal menyimpan informasi QRIS';
                        }
                    } else {
                        $error = 'Gagal upload file QRIS';
                    }
                } else {
                    if (!empty($partner['qris_image'])) {
                        $stmt = $db->prepare("UPDATE laundry_partners SET payment_type = ? WHERE id = ?");
                        if ($stmt->execute(['qris', $partner_id])) {
                            $success = 'Metode pembayaran diubah ke QRIS';
                            $partner['payment_type'] = 'qris';
                        }
                    } else {
                        $error = 'Upload gambar QRIS terlebih dahulu';
                    }
                }
            }
            break;
            
        case 'update_account':
            $full_name = sanitize($_POST['full_name']);
            $phone = sanitize($_POST['phone']);
            $email = sanitize($_POST['email']);
            
            if (empty($full_name) || empty($phone) || empty($email)) {
                $error = 'Semua field harus diisi';
                break;
            }
            
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetch()) {
                $error = 'Email sudah digunakan pengguna lain';
                break;
            }
            
            $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?");
            if ($stmt->execute([$full_name, $phone, $email, $user['id']])) {
                $success = 'Informasi akun berhasil diperbarui';
                $user['full_name'] = $full_name;
                $user['phone'] = $phone;
                $user['email'] = $email;
            } else {
                $error = 'Gagal memperbarui informasi akun';
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'Semua field password harus diisi';
                break;
            }
            
            if (!password_verify($current_password, $user['password_hash'])) {
                $error = 'Password lama tidak sesuai';
                break;
            }
            
            if (strlen($new_password) < 6) {
                $error = 'Password baru minimal 6 karakter';
                break;
            }
            
            if ($new_password !== $confirm_password) {
                $error = 'Konfirmasi password tidak cocok';
                break;
            }
            
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([$password_hash, $user['id']])) {
                $success = 'Password berhasil diubah';
            } else {
                $error = 'Gagal mengubah password';
            }
            break;
    }
}

// Get services count
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(is_active = TRUE) as active FROM services WHERE partner_id = ?");
$stmt->execute([$partner_id]);
$services_stats = $stmt->fetch();

// Get total orders
$stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE partner_id = ?");
$stmt->execute([$partner_id]);
$total_orders = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - <?php echo htmlspecialchars($partner['laundry_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { border-radius: 12px; }
        .btn { border-radius: 12px; }
        .tab-active { border-bottom: 3px solid #8b5cf6; color: #8b5cf6; }
        .image-preview-container {
            position: relative;
            width: 100%;
            height: 300px;
            border-radius: 12px;
            overflow: hidden;
            background: #f3f4f6;
        }
        .image-preview-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .image-preview-container:hover .image-overlay {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <a href="dashboard.php" class="mr-4">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">Profil Mitra</h1>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($partner['laundry_name']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Profile Header Card -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 card shadow-lg p-8 mb-6 text-white">
            <div class="flex items-center space-x-6">
                <div class="w-24 h-24 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <h2 class="text-3xl font-bold mb-1"><?php echo htmlspecialchars($partner['laundry_name']); ?></h2>
                    <p class="text-purple-100 mb-3"><?php echo htmlspecialchars($partner['laundry_address']); ?></p>
                    <div class="flex items-center space-x-6 text-sm">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <?php echo $total_orders; ?> Pesanan
                        </div>
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            <?php echo $services_stats['active']; ?> Layanan Aktif
                        </div>
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                            </svg>
                            <?php echo number_format($partner['rating'], 1); ?> Rating
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="bg-white card shadow-md mb-6 overflow-hidden">
            <div class="flex border-b border-gray-200 overflow-x-auto">
                <button onclick="showTab('business')" class="tab-button tab-active px-6 py-4 font-semibold transition whitespace-nowrap" data-tab="business">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    Info Bisnis
                </button>
                <button onclick="showTab('image')" class="tab-button px-6 py-4 font-semibold text-gray-600 hover:text-purple-600 transition whitespace-nowrap" data-tab="image">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Gambar Laundry
                </button>
                <button onclick="showTab('services')" class="tab-button px-6 py-4 font-semibold text-gray-600 hover:text-purple-600 transition whitespace-nowrap" data-tab="services">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    Layanan
                </button>
                <button onclick="showTab('payment')" class="tab-button px-6 py-4 font-semibold text-gray-600 hover:text-purple-600 transition whitespace-nowrap" data-tab="payment">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    Pembayaran
                </button>
                <button onclick="showTab('account')" class="tab-button px-6 py-4 font-semibold text-gray-600 hover:text-purple-600 transition whitespace-nowrap" data-tab="account">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Akun
                </button>
            </div>
        </div>

        <!-- Tab Contents -->
        <div id="tab-content">
            <!-- Business Info Tab -->
            <div id="business-tab" class="tab-content">
                <div class="bg-white card shadow-md p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-6">Informasi Bisnis</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_business_info">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Laundry *</label>
                                <input type="text" name="laundry_name" value="<?php echo htmlspecialchars($partner['laundry_name']); ?>" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-semibold mb-2">Biaya Antar Jemput (Rp)</label>
                                <input type="number" name="delivery_fee" min="0" step="500" value="<?php echo $partner['delivery_fee']; ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-semibold mb-2">Alamat Lengkap *</label>
                            <textarea name="laundry_address" rows="3" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"><?php echo htmlspecialchars($partner['laundry_address']); ?></textarea>
                        </div>
                        
                        <div class="mb-6">
                            <label class="flex items-center">
                                <input type="checkbox" name="has_pickup_delivery" value="1" <?php echo $partner['has_pickup_delivery'] ? 'checked' : ''; ?>
                                    class="w-5 h-5 text-purple-500 rounded focus:ring-2 focus:ring-purple-500">
                                <span class="ml-3 text-gray-700 font-medium">Menyediakan layanan antar jemput</span>
                            </label>
                            <p class="text-sm text-gray-500 ml-8 mt-1">Aktifkan jika Anda menyediakan layanan pickup dan delivery untuk customer</p>
                        </div>
                        
                        <button type="submit" class="bg-purple-500 hover:bg-purple-600 text-white font-semibold px-8 py-3 btn transition duration-200">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Image Tab - NEW -->
            <div id="image-tab" class="tab-content hidden">
                <div class="bg-white card shadow-md p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-6">Gambar Laundry</h3>
                    
                    <!-- Current Image Preview -->
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-semibold mb-3">Preview Gambar</label>
                        <div class="image-preview-container">
                            <?php if (!empty($partner['image_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($partner['image_url']); ?>" alt="Laundry Image" id="currentImage">
                                <div class="image-overlay">
                                    <form method="POST" action="" onsubmit="return confirm('Yakin ingin menghapus gambar ini?');">
                                        <input type="hidden" name="action" value="delete_laundry_image">
                                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-semibold px-6 py-3 rounded-lg transition duration-200">
                                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                            Hapus Gambar
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                    <svg class="w-24 h-24 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <p class="text-lg font-semibold">Belum ada gambar</p>
                                    <p class="text-sm">Upload gambar laundry Anda untuk menarik pelanggan</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Upload Form -->
                    <form method="POST" action="" enctype="multipart/form-data" id="uploadImageForm">
                        <input type="hidden" name="action" value="upload_laundry_image">
                        
                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <?php echo !empty($partner['image_url']) ? 'Ganti Gambar Laundry' : 'Upload Gambar Laundry'; ?>
                            </label>
                            <input type="file" name="laundry_image" id="laundryImageInput" accept="image/jpeg,image/jpg,image/png,image/webp" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                onchange="previewImage(this)">
                            <p class="text-xs text-gray-500 mt-2">Format: JPG, PNG, WEBP | Maksimal: 5MB | Rekomendasi: 1200x800px</p>
                        </div>

                        <!-- Image Preview Before Upload -->
                        <div id="imagePreview" class="hidden mb-6">
                            <label class="block text-gray-700 text-sm font-semibold mb-2">Preview Gambar Baru</label>
                            <div class="image-preview-container">
                                <img id="previewImg" src="" alt="Preview">
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-blue-500 mr-3 mt-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2">Tips Foto Laundry yang Bagus:</h4>
                                    <ul class="text-sm text-gray-700 space-y-1">
                                        <li>• Gunakan pencahayaan yang baik dan terang</li>
                                        <li>• Pastikan toko terlihat bersih dan rapi</li>
                                        <li>• Ambil dari sudut yang menampilkan ruangan secara keseluruhan</li>
                                        <li>• Hindari foto yang buram atau gelap</li>
                                        <li>• Tampilkan mesin cuci atau area kerja yang profesional</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-purple-500 hover:bg-purple-600 text-white font-semibold px-8 py-3 btn transition duration-200">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            <?php echo !empty($partner['image_url']) ? 'Ganti Gambar' : 'Upload Gambar'; ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Services Tab -->
            <div id="services-tab" class="tab-content hidden">
                <div class="bg-white card shadow-md p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Kelola Layanan</h3>
                            <p class="text-gray-600 text-sm mt-1">Total: <?php echo $services_stats['total']; ?> layanan (<?php echo $services_stats['active']; ?> aktif)</p>
                        </div>
                        <a href="services.php" class="bg-purple-500 hover:bg-purple-600 text-white font-semibold px-6 py-3 btn transition duration-200">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Kelola Layanan
                        </a>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                        <div class="flex items-start">
                            <svg class="w-6 h-6 text-blue-500 mr-3 mt-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold text-gray-800 mb-2">Tentang Layanan</h4>
                                <ul class="text-sm text-gray-700 space-y-1">
                                    <li>• Tambahkan berbagai jenis layanan laundry yang Anda tawarkan</li>
                                    <li>• Setiap layanan dapat memiliki harga dan durasi yang berbeda</li>
                                    <li>• Customer akan melihat layanan aktif Anda di aplikasi</li>
                                    <li>• Anda dapat menonaktifkan layanan tanpa menghapusnya</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Tab -->
            <div id="payment-tab" class="tab-content hidden">
                <div class="bg-white card shadow-md p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-6">Informasi Pembayaran</h3>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_payment_info">
                        
                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-semibold mb-3">Metode Pembayaran</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="relative flex items-center p-4 border-2 rounded-lg cursor-pointer hover:border-purple-500 transition <?php echo $partner['payment_type'] === 'qris' ? 'border-purple-500 bg-purple-50' : 'border-gray-200'; ?>">
                                    <input type="radio" name="payment_type" value="qris" <?php echo $partner['payment_type'] === 'qris' ? 'checked' : ''; ?> 
                                        onchange="togglePaymentFields(this.value)"
                                        class="w-5 h-5 text-purple-500">
                                    <div class="ml-3">
                                        <span class="font-semibold text-gray-800 block">QRIS</span>
                                        <span class="text-sm text-gray-600">Quick Response Code Indonesian Standard</span>
                                    </div>
                                </label>
                                
                                <label class="relative flex items-center p-4 border-2 rounded-lg cursor-pointer hover:border-purple-500 transition <?php echo $partner['payment_type'] === 'bank' ? 'border-purple-500 bg-purple-50' : 'border-gray-200'; ?>">
                                    <input type="radio" name="payment_type" value="bank" <?php echo $partner['payment_type'] === 'bank' ? 'checked' : ''; ?>
                                        onchange="togglePaymentFields(this.value)"
                                        class="w-5 h-5 text-purple-500">
                                    <div class="ml-3">
                                        <span class="font-semibold text-gray-800 block">Transfer Bank</span>
                                        <span class="text-sm text-gray-600">Rekening bank konvensional</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- QRIS Fields -->
                        <div id="qris-fields" class="<?php echo $partner['payment_type'] !== 'qris' ? 'hidden' : ''; ?>">
                            <div class="mb-6">
                                <label class="block text-gray-700 text-sm font-semibold mb-2">Upload Gambar QRIS</label>
                                <?php if (!empty($partner['qris_image'])): ?>
                                    <div class="mb-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                        <p class="text-sm text-gray-600 mb-2">QRIS saat ini:</p>
                                        <img src="../<?php echo htmlspecialchars($partner['qris_image']); ?>" alt="QRIS" class="max-w-xs rounded-lg shadow-md">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="qris_image" accept="image/jpeg,image/jpg,image/png"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <p class="text-xs text-gray-500 mt-2">Format: JPG, PNG | Maksimal: 2MB</p>
                            </div>
                        </div>
                        
                        <!-- Bank Fields -->
                        <div id="bank-fields" class="<?php echo $partner['payment_type'] !== 'bank' ? 'hidden' : ''; ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Bank *</label>
                                    <select name="bank_name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                        <option value="">Pilih Bank</option>
                                        <option value="BCA" <?php echo $partner['bank_name'] === 'BCA' ? 'selected' : ''; ?>>BCA</option>
                                        <option value="Mandiri" <?php echo $partner['bank_name'] === 'Mandiri' ? 'selected' : ''; ?>>Mandiri</option>
                                        <option value="BNI" <?php echo $partner['bank_name'] === 'BNI' ? 'selected' : ''; ?>>BNI</option>
                                        <option value="BRI" <?php echo $partner['bank_name'] === 'BRI' ? 'selected' : ''; ?>>BRI</option>
                                        <option value="CIMB Niaga" <?php echo $partner['bank_name'] === 'CIMB Niaga' ? 'selected' : ''; ?>>CIMB Niaga</option>
                                        <option value="Permata" <?php echo $partner['bank_name'] === 'Permata' ? 'selected' : ''; ?>>Permata</option>
                                        <option value="Danamon" <?php echo $partner['bank_name'] === 'Danamon' ? 'selected' : ''; ?>>Danamon</option>
                                        <option value="BSI" <?php echo $partner['bank_name'] === 'BSI' ? 'selected' : ''; ?>>BSI (Bank Syariah Indonesia)</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-semibold mb-2">Nomor Rekening *</label>
                                    <input type="text" name="bank_account_number" value="<?php echo htmlspecialchars($partner['bank_account_number'] ?? ''); ?>"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                        placeholder="1234567890">
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Pemilik Rekening *</label>
                                <input type="text" name="bank_account_name" value="<?php echo htmlspecialchars($partner['bank_account_name'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                    placeholder="Nama sesuai rekening bank">
                            </div>
                        </div>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-yellow-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <p class="text-sm text-gray-700">
                                    <strong>Penting:</strong> Pastikan informasi pembayaran Anda benar. Customer akan menggunakan informasi ini untuk melakukan pembayaran.
                                </p>
                            </div>
                        </div>
                        
                        <button type="submit" class="bg-purple-500 hover:bg-purple-600 text-white font-semibold px-8 py-3 btn transition duration-200">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account Tab -->
            <div id="account-tab" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Account Information -->
                    <div class="bg-white card shadow-md p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">Informasi Akun</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_account">
                            
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-semibold mb-2">Nama Lengkap *</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-semibold mb-2">Email *</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 text-sm font-semibold mb-2">Nomor Telepon *</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <button type="submit" class="w-full bg-purple-500 hover:bg-purple-600 text-white font-semibold px-8 py-3 btn transition duration-200">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Perbarui Informasi
                            </button>
                        </form>
                    </div>
                    
                    <!-- Change Password -->
                    <div class="bg-white card shadow-md p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">Ubah Password</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-semibold mb-2">Password Lama *</label>
                                <input type="password" name="current_password" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-semibold mb-2">Password Baru *</label>
                                <input type="password" name="new_password" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <p class="text-xs text-gray-500 mt-1">Minimal 6 karakter</p>
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 text-sm font-semibold mb-2">Konfirmasi Password Baru *</label>
                                <input type="password" name="confirm_password" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white font-semibold px-8 py-3 btn transition duration-200">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                </svg>
                                Ubah Password
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Account Stats -->
                <div class="bg-white card shadow-md p-6 mt-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Informasi Akun</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Tipe Akun</p>
                            <p class="text-lg font-semibold text-gray-800">Mitra Laundry</p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Status</p>
                            <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 text-sm font-semibold rounded-full">
                                <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                Aktif
                            </span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Bergabung Sejak</p>
                            <p class="text-lg font-semibold text-gray-800"><?php echo formatDate($user['created_at'], 'd M Y'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('tab-active', 'text-purple-600');
                btn.classList.add('text-gray-600');
            });
            
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            
            const activeButton = document.querySelector(`[data-tab="${tabName}"]`);
            activeButton.classList.add('tab-active', 'text-purple-600');
            activeButton.classList.remove('text-gray-600');
        }
        
        // Toggle payment fields
        function togglePaymentFields(paymentType) {
            const qrisFields = document.getElementById('qris-fields');
            const bankFields = document.getElementById('bank-fields');
            
            if (paymentType === 'qris') {
                qrisFields.classList.remove('hidden');
                bankFields.classList.add('hidden');
                
                bankFields.querySelectorAll('input, select').forEach(field => {
                    field.removeAttribute('required');
                });
            } else {
                qrisFields.classList.add('hidden');
                bankFields.classList.remove('hidden');
                
                bankFields.querySelectorAll('input[name="bank_name"], input[name="bank_account_number"], input[name="bank_account_name"]').forEach(field => {
                    field.setAttribute('required', 'required');
                });
                
                qrisFields.querySelectorAll('input').forEach(field => {
                    field.removeAttribute('required');
                });
            }
        }
        
        // Preview image before upload
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Ukuran file terlalu besar! Maksimal 5MB');
                    input.value = '';
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format file harus JPG, PNG, atau WEBP');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Initialize payment fields on page load
        document.addEventListener('DOMContentLoaded', function() {
            const selectedPayment = document.querySelector('input[name="payment_type"]:checked');
            if (selectedPayment) {
                togglePaymentFields(selectedPayment.value);
            }
            
            // Auto-hide success/error messages
            setTimeout(function() {
                const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
                messages.forEach(function(msg) {
                    msg.style.transition = 'opacity 0.5s ease-out';
                    msg.style.opacity = '0';
                    setTimeout(() => msg.remove(), 500);
                });
            }, 5000);
        });
    </script>
</body>
</html>