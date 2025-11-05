<?php
session_start();
require_once '../config.php';
require_once '../helpers.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Profile
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        
        if (!empty($full_name) && !empty($phone)) {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("ssi", $full_name, $phone, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Profil berhasil diperbarui!';
            } else {
                $_SESSION['error_message'] = 'Gagal memperbarui profil!';
            }
        }
        header('Location: profile.php');
        exit;
    }
    
    // Update Email
    if (isset($_POST['update_email'])) {
        $new_email = trim($_POST['new_email']);
        $password = $_POST['password'];
        
        $userQuery = $conn->query("SELECT password_hash FROM users WHERE id = $user_id");
        $user = $userQuery->fetch_assoc();
        
        if (password_verify($password, $user['password_hash'])) {
            $checkEmail = $conn->query("SELECT id FROM users WHERE email = '$new_email' AND id != $user_id");
            
            if ($checkEmail->num_rows > 0) {
                $_SESSION['error_message'] = 'Email sudah digunakan!';
            } else {
                $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->bind_param("si", $new_email, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Email berhasil diperbarui!';
                } else {
                    $_SESSION['error_message'] = 'Gagal memperbarui email!';
                }
            }
        } else {
            $_SESSION['error_message'] = 'Password salah!';
        }
        
        header('Location: profile.php');
        exit;
    }
    
    // Change Password
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $userQuery = $conn->query("SELECT password_hash FROM users WHERE id = $user_id");
        $user = $userQuery->fetch_assoc();
        
        if (password_verify($current_password, $user['password_hash'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_hash, $user_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = 'Password berhasil diubah!';
                    } else {
                        $_SESSION['error_message'] = 'Gagal mengubah password!';
                    }
                } else {
                    $_SESSION['error_message'] = 'Password minimal 6 karakter!';
                }
            } else {
                $_SESSION['error_message'] = 'Konfirmasi password tidak cocok!';
            }
        } else {
            $_SESSION['error_message'] = 'Password lama salah!';
        }
        
        header('Location: profile.php');
        exit;
    }
    
    // Upload Avatar
    if (isset($_POST['upload_avatar']) && isset($_FILES['avatar'])) {
        $file = $_FILES['avatar'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $file['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed) && $file['size'] <= 5242880) {
            $upload_dir = '../uploads/avatars/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = time() . '_' . rand(1000, 9999) . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $db_path = 'uploads/avatars/' . $new_filename;
                $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $stmt->bind_param("si", $db_path, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Foto profil berhasil diperbarui!';
                }
            }
        } else {
            $_SESSION['error_message'] = 'File tidak valid! (Max 5MB, format: JPG, PNG, GIF)';
        }
        
        header('Location: profile.php');
        exit;
    }
    
    // Add Address
    if (isset($_POST['add_address'])) {
        $address_label = trim($_POST['address_label']);
        $full_address = trim($_POST['full_address']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        if ($is_default) {
            $conn->query("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = $user_id");
        }
        
        $stmt = $conn->prepare("INSERT INTO customer_addresses (customer_id, address_label, full_address, is_default) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $user_id, $address_label, $full_address, $is_default);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Alamat berhasil ditambahkan!';
        }
        
        header('Location: profile.php');
        exit;
    }
    
    // Delete Address
    if (isset($_POST['delete_address'])) {
        $address_id = intval($_POST['address_id']);
        $conn->query("DELETE FROM customer_addresses WHERE id = $address_id AND customer_id = $user_id");
        $_SESSION['success_message'] = 'Alamat berhasil dihapus!';
        
        header('Location: profile.php');
        exit;
    }
}

// Get user data
$userQuery = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user = $userQuery->fetch_assoc();

// Get addresses
$addressQuery = $conn->query("SELECT * FROM customer_addresses WHERE customer_id = $user_id ORDER BY is_default DESC, created_at DESC");
$addresses = [];
while ($row = $addressQuery->fetch_assoc()) {
    $addresses[] = $row;
}

// Get order stats
$statsQuery = $conn->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status IN ('pending', 'paid', 'pickup', 'processing') THEN 1 ELSE 0 END) as active_orders
    FROM orders 
    WHERE customer_id = $user_id
");
$stats = $statsQuery->fetch_assoc();

include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - MICU Laundry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }

        /* Container */
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* Page Title */
        .page-header {
            margin-bottom: 30px;
            animation: fadeInUp 0.5s ease-out;
        }

        .page-title {
            font-size: 28px;
            color: #333;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 15px;
            color: #666;
        }

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInDown 0.4s ease-out;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        .alert i {
            font-size: 20px;
        }

        .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 20px;
            color: inherit;
            opacity: 0.5;
            cursor: pointer;
            transition: opacity 0.3s;
        }

        .alert-close:hover {
            opacity: 1;
        }

        @keyframes slideInDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Profile Header Card */
        .profile-header-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            animation: fadeInUp 0.5s ease-out 0.1s backwards;
        }

        .profile-main {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid #e9ecef;
        }

        /* Avatar */
        .avatar-container {
            position: relative;
            flex-shrink: 0;
        }

        .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #f0f0f0;
        }

        .avatar-edit-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 36px;
            height: 36px;
            background: #0066cc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0, 102, 204, 0.3);
        }

        .avatar-edit-btn:hover {
            background: #0052a3;
            transform: scale(1.1);
        }

        .avatar-edit-btn i {
            color: white;
            font-size: 16px;
        }

        .avatar-edit-btn input {
            display: none;
        }

        /* Profile Info */
        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 24px;
            color: #333;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .profile-details {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }

        .detail-item i {
            color: #0066cc;
            width: 16px;
            text-align: center;
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .stat-box {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .stat-box:hover {
            background: #e8f3ff;
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #0066cc;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: #666;
        }

        /* Tabs Navigation */
        .tabs-nav {
            display: flex;
            gap: 0;
            background: white;
            border-radius: 12px;
            padding: 6px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow-x: auto;
            animation: fadeInUp 0.5s ease-out 0.2s backwards;
        }

        .tab-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            background: transparent;
            color: #666;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 8px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .tab-btn:hover {
            color: #0066cc;
            background: #f8f9fa;
        }

        .tab-btn.active {
            background: #0066cc;
            color: white;
        }

        .tab-btn i {
            font-size: 16px;
        }

        /* Tab Content */
        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease-out;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Section Card */
        .section-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #e8f3ff 0%, #d4e8ff 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-icon i {
            color: #0066cc;
            font-size: 18px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }

        .section-description {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-label i {
            color: #0066cc;
            font-size: 12px;
        }

        .form-input {
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: #0066cc;
            box-shadow: 0 0 0 4px rgba(0, 102, 204, 0.1);
        }

        .form-input:disabled {
            background: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }

        .form-hint {
            font-size: 12px;
            color: #999;
            margin-top: -4px;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #0066cc;
            color: white;
        }

        .btn-primary:hover {
            background: #0052a3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.3);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .btn-outline {
            background: white;
            color: #0066cc;
            border: 2px solid #0066cc;
        }

        .btn-outline:hover {
            background: #0066cc;
            color: white;
        }

        .btn-full {
            width: 100%;
        }

        .btn i {
            font-size: 14px;
        }

        /* Address List */
        .address-list {
            display: grid;
            gap: 15px;
        }

        .address-item {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 18px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            transition: all 0.3s ease;
        }

        .address-item:hover {
            border-color: #0066cc;
            background: #f8fbff;
        }

        .address-item.default {
            border-color: #28a745;
            background: #f0f9f4;
        }

        .address-content {
            flex: 1;
        }

        .address-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .address-label {
            font-weight: 700;
            color: #333;
            font-size: 15px;
        }

        .default-badge {
            background: #28a745;
            color: white;
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 600;
        }

        .address-text {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }

        .address-actions {
            display: flex;
            gap: 8px;
            margin-left: 15px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-icon-danger {
            background: #ffebee;
            color: #dc3545;
        }

        .btn-icon-danger:hover {
            background: #dc3545;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .add-address-btn {
            width: 100%;
            padding: 16px;
            border: 2px dashed #ccc;
            border-radius: 12px;
            background: white;
            color: #0066cc;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 15px;
        }

        .add-address-btn:hover {
            border-color: #0066cc;
            background: #f8fbff;
        }

        /* Settings Box */
        .settings-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .settings-box.danger {
            background: #fff5f5;
            border: 2px solid #ffebee;
        }

        .settings-title {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .settings-title i {
            color: #0066cc;
        }

        .settings-box.danger .settings-title i {
            color: #dc3545;
        }

        .settings-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }

        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease-out;
        }

        .modal.active {
            display: flex;
        }

        .modal-dialog {
            background: white;
            border-radius: 16px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease-out;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 25px 25px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-title i {
            color: #0066cc;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f0f0f0;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: #0066cc;
            color: white;
        }

        .modal-body {
            padding: 25px;
        }

        /* Checkbox */
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .checkbox-label:hover {
            background: #e9ecef;
        }

        .checkbox-label input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-container {
                padding: 20px 15px;
            }

            .page-title {
                font-size: 24px;
            }

            .profile-main {
                flex-direction: column;
                text-align: center;
            }

            .profile-details {
                align-items: center;
            }

            .stats-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .tabs-nav {
                padding: 4px;
            }

            .tab-btn {
                padding: 10px 12px;
                font-size: 13px;
            }

            .tab-btn span {
                display: none;
            }

            .section-card {
                padding: 20px;
            }

            .address-item {
                flex-direction: column;
                gap: 15px;
            }

            .address-actions {
                margin-left: 0;
                width: 100%;
            }

            .btn-icon {
                flex: 1;
            }

            .button-group {
                flex-direction: column;
            }

            .button-group .btn {
                width: 100%;
            }
        }

        /* Loading State */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0066cc;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <div class="profile-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Profil Saya</h1>
            <p class="page-subtitle">Kelola informasi profil Anda untuk keamanan akun</p>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></span>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
        <?php endif; ?>

        <!-- Profile Header Card -->
        <div class="profile-header-card">
            <div class="profile-main">
                <div class="avatar-container">
                    <img src="<?php echo !empty($user['profile_image']) ? '../' . htmlspecialchars($user['profile_image']) : 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&size=100&background=0066cc&color=fff'; ?>" 
                         alt="Avatar" class="avatar" id="avatarPreview">
                    <form method="POST" enctype="multipart/form-data" id="avatarForm">
                        <label class="avatar-edit-btn">
                            <i class="fas fa-camera"></i>
                            <input type="file" name="avatar" accept="image/*" onchange="uploadAvatar(event)">
                        </label>
                    </form>
                </div>

                <div class="profile-info">
                    <h2 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <div class="profile-details">
                        <div class="detail-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($user['phone']); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Bergabung sejak <?php echo date('d M Y', strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                    <div class="stat-label">Total Pesanan</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $stats['completed_orders']; ?></div>
                    <div class="stat-label">Selesai</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $stats['active_orders']; ?></div>
                    <div class="stat-label">Sedang Berjalan</div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs-nav">
            <button class="tab-btn active" onclick="switchTab('profile')">
                <i class="fas fa-user"></i>
                <span>Data Diri</span>
            </button>
            <button class="tab-btn" onclick="switchTab('address')">
                <i class="fas fa-map-marker-alt"></i>
                <span>Alamat</span>
            </button>
            <button class="tab-btn" onclick="switchTab('security')">
                <i class="fas fa-lock"></i>
                <span>Keamanan</span>
            </button>
            <button class="tab-btn" onclick="switchTab('settings')">
                <i class="fas fa-cog"></i>
                <span>Pengaturan</span>
            </button>
        </div>

        <!-- Tab: Data Diri -->
        <div class="tab-content active" id="tab-profile">
            <div class="section-card">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div>
                        <h3 class="section-title">Edit Data Diri</h3>
                        <p class="section-description">Perbarui nama dan nomor telepon Anda</p>
                    </div>
                </div>

                <form method="POST" class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i>
                            Nama Lengkap
                        </label>
                        <input type="text" name="full_name" class="form-input" 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                               placeholder="Masukkan nama lengkap" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-phone"></i>
                            Nomor Telepon
                        </label>
                        <input type="tel" name="phone" class="form-input" 
                               value="<?php echo htmlspecialchars($user['phone']); ?>" 
                               placeholder="Contoh: 081234567890" required>
                        <span class="form-hint">Nomor ini akan digunakan untuk konfirmasi pesanan</span>
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-primary btn-full">
                        <i class="fas fa-save"></i>
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>

        <!-- Tab: Alamat -->
        <div class="tab-content" id="tab-address">
            <div class="section-card">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div>
                        <h3 class="section-title">Alamat Tersimpan</h3>
                        <p class="section-description">Kelola alamat pengiriman laundry Anda</p>
                    </div>
                </div>

                <div class="address-list">
                    <?php if (count($addresses) > 0): ?>
                        <?php foreach ($addresses as $address): ?>
                        <div class="address-item <?php echo $address['is_default'] ? 'default' : ''; ?>">
                            <div class="address-content">
                                <div class="address-header">
                                    <i class="fas fa-home" style="color: #0066cc;"></i>
                                    <span class="address-label"><?php echo htmlspecialchars($address['address_label']); ?></span>
                                    <?php if ($address['is_default']): ?>
                                    <span class="default-badge">Alamat Utama</span>
                                    <?php endif; ?>
                                </div>
                                <div class="address-text"><?php echo htmlspecialchars($address['full_address']); ?></div>
                            </div>
                            <div class="address-actions">
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Hapus alamat ini?');">
                                    <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                    <button type="submit" name="delete_address" class="btn-icon btn-icon-danger" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-map-marked-alt"></i>
                            <p>Belum ada alamat tersimpan</p>
                        </div>
                    <?php endif; ?>
                </div>

                <button class="add-address-btn" onclick="openModal('addressModal')">
                    <i class="fas fa-plus"></i>
                    Tambah Alamat Baru
                </button>
            </div>
        </div>

        <!-- Tab: Keamanan -->
        <div class="tab-content" id="tab-security">
            <div class="section-card">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <h3 class="section-title">Ubah Email</h3>
                        <p class="section-description">Perbarui alamat email Anda</p>
                    </div>
                </div>

                <form method="POST" class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i>
                            Email Saat Ini
                        </label>
                        <input type="email" class="form-input" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i>
                            Email Baru
                        </label>
                        <input type="email" name="new_email" class="form-input" 
                               placeholder="Masukkan email baru" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i>
                            Konfirmasi Password
                        </label>
                        <input type="password" name="password" class="form-input" 
                               placeholder="Masukkan password Anda" required>
                        <span class="form-hint">Untuk keamanan, konfirmasi dengan password Anda</span>
                    </div>

                    <button type="submit" name="update_email" class="btn btn-primary btn-full">
                        <i class="fas fa-save"></i>
                        Ubah Email
                    </button>
                </form>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <div>
                        <h3 class="section-title">Ganti Password</h3>
                        <p class="section-description">Buat password yang kuat untuk keamanan akun</p>
                    </div>
                </div>

                <form method="POST" class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i>
                            Password Lama
                        </label>
                        <input type="password" name="current_password" class="form-input" 
                               placeholder="Masukkan password lama" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-key"></i>
                            Password Baru
                        </label>
                        <input type="password" name="new_password" class="form-input" 
                               placeholder="Masukkan password baru" required minlength="6">
                        <span class="form-hint">Minimal 6 karakter</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-check-circle"></i>
                            Konfirmasi Password Baru
                        </label>
                        <input type="password" name="confirm_password" class="form-input" 
                               placeholder="Ketik ulang password baru" required minlength="6">
                    </div>

                    <button type="submit" name="change_password" class="btn btn-primary btn-full">
                        <i class="fas fa-lock"></i>
                        Ubah Password
                    </button>
                </form>
            </div>
        </div>

        <!-- Tab: Pengaturan -->
        <div class="tab-content" id="tab-settings">
            <div class="section-card">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div>
                        <h3 class="section-title">Notifikasi</h3>
                        <p class="section-description">Kelola preferensi notifikasi Anda</p>
                    </div>
                </div>

                <label class="checkbox-label">
                    <input type="checkbox" checked>
                    <span>Terima notifikasi email untuk pesanan baru</span>
                </label>
            </div>

            <div class="section-card">
                <div class="settings-box danger">
                    <h4 class="settings-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        Zona Bahaya
                    </h4>
                    <p class="settings-description">Tindakan berikut tidak dapat dibatalkan</p>
                    
                    <div class="button-group">
                        <a href="../logout.php" class="btn btn-danger" onclick="return confirm('Yakin ingin keluar dari akun?');">
                            <i class="fas fa-sign-out-alt"></i>
                            Keluar dari Akun
                        </a>
                        <button class="btn btn-outline" style="color: #dc3545; border-color: #dc3545;" 
                                onclick="alert('Fitur ini sedang dalam pengembangan.\n\nUntuk menghapus akun, silakan hubungi customer service.')">
                            <i class="fas fa-user-times"></i>
                            Hapus Akun Permanen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Address Modal -->
    <div class="modal" id="addressModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-plus"></i>
                    Tambah Alamat Baru
                </h3>
                <button class="modal-close" onclick="closeModal('addressModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tag"></i>
                            Label Alamat
                        </label>
                        <input type="text" name="address_label" class="form-input" 
                               placeholder="Contoh: Rumah, Kantor, Kos" required>
                        <span class="form-hint">Beri nama untuk memudahkan identifikasi</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-map-marker-alt"></i>
                            Alamat Lengkap
                        </label>
                        <textarea name="full_address" class="form-input" rows="4" 
                                  placeholder="Contoh: Jl. Merdeka No. 123, RT 01/RW 02, Kelurahan ABC, Kecamatan DEF" required></textarea>
                        <span class="form-hint">Sertakan detail seperti RT/RW, patokan, atau nomor rumah</span>
                    </div>

                    <label class="checkbox-label">
                        <input type="checkbox" name="is_default">
                        <span>Jadikan sebagai alamat utama</span>
                    </label>

                    <button type="submit" name="add_address" class="btn btn-primary btn-full">
                        <i class="fas fa-save"></i>
                        Simpan Alamat
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab Switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Add active to clicked button
            event.target.closest('.tab-btn').classList.add('active');
        }

        // Avatar Upload
        function uploadAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                // Show loading
                document.getElementById('loadingOverlay').classList.add('active');
                
                // Preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                };
                reader.readAsDataURL(file);

                // Auto submit form
                const form = document.getElementById('avatarForm');
                const formData = new FormData(form);
                formData.append('upload_avatar', '1');

                fetch('profile.php', {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    if (response.ok) {
                        location.reload();
                    } else {
                        alert('Gagal mengunggah foto. Silakan coba lagi.');
                        document.getElementById('loadingOverlay').classList.remove('active');
                    }
                }).catch(error => {
                    alert('Terjadi kesalahan. Silakan coba lagi.');
                    document.getElementById('loadingOverlay').classList.remove('active');
                });
            }
        }

        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close modal on backdrop click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.animation = 'slideInDown 0.4s ease-out reverse';
                setTimeout(() => alert.remove(), 400);
            });
        }, 5000);

        // Form validation for password change
        const passwordForms = document.querySelectorAll('form');
        passwordForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const newPass = this.querySelector('input[name="new_password"]');
                const confirmPass = this.querySelector('input[name="confirm_password"]');
                
                if (newPass && confirmPass) {
                    if (newPass.value !== confirmPass.value) {
                        e.preventDefault();
                        alert('Password baru dan konfirmasi password tidak sama!');
                        confirmPass.focus();
                    }
                }
            });
        });

        // Prevent accidental form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>