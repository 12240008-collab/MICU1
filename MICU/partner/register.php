<?php
session_start();
require_once '../config.php';

if (isLoggedIn()) {
    redirect('partner/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $laundry_name = sanitize($_POST['laundry_name'] ?? '');
    $owner_name = sanitize($_POST['owner_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $has_delivery = isset($_POST['has_delivery']) ? 1 : 0;
    
    // Validation
    if (empty($laundry_name) || empty($owner_name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'Semua field wajib harus diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak cocok';
    } else {
        $db = db();
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar';
        } else {
            try {
                $db->beginTransaction();
                
                // Insert user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    INSERT INTO users (user_type, full_name, email, phone, password_hash)
                    VALUES ('partner', ?, ?, ?, ?)
                ");
                $stmt->execute([$owner_name, $email, $phone, $password_hash]);
                $user_id = $db->lastInsertId();
                
                // Insert laundry partner
                $stmt = $db->prepare("
                    INSERT INTO laundry_partners (user_id, laundry_name, laundry_address, has_pickup_delivery, is_active)
                    VALUES (?, ?, ?, ?, TRUE)
                ");
                $stmt->execute([$user_id, $laundry_name, $address, $has_delivery]);
                
                $db->commit();
                
                $success = 'Registrasi berhasil! Silakan login.';
                header("refresh:2;url=login.php");
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Terjadi kesalahan. Silakan coba lagi.';
                // For debugging (remove in production):
                // $error .= ' Error: ' . $e->getMessage();
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
    <title>Daftar Mitra - MICU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { border-radius: 12px; }
        .btn { border-radius: 12px; }
        .checkbox-custom {
            appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid #d1d5db;
            border-radius: 4px;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }
        .checkbox-custom:checked {
            border-color: #8b5cf6;
            background-color: #8b5cf6;
        }
        .checkbox-custom:checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 14px;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-50 to-purple-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md my-8">
        <!-- Logo -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-purple-500 rounded-full mb-3">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Daftar Mitra Laundry</h1>
            <p class="text-gray-600 text-sm">Bergabung dengan MICU</p>
        </div>

        <!-- Registration Card -->
        <div class="bg-white card shadow-xl p-8">
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $success; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-4">
                <!-- Laundry Name -->
                <div>
                    <label for="laundry_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Nama Laundry <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="laundry_name" 
                        name="laundry_name" 
                        value="<?php echo htmlspecialchars($_POST['laundry_name'] ?? ''); ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" 
                        placeholder="Contoh: Laundry Bersih Kilat"
                        required
                    >
                </div>

                <!-- Owner Name -->
                <div>
                    <label for="owner_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Nama Pemilik <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="owner_name" 
                        name="owner_name" 
                        value="<?php echo htmlspecialchars($_POST['owner_name'] ?? ''); ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" 
                        placeholder="Nama lengkap Anda"
                        required
                    >
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" 
                        placeholder="email@contoh.com"
                        required
                    >
                </div>

                <!-- Phone -->
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                        Nomor Telepon <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" 
                        placeholder="08xxxxxxxxxx"
                        required
                    >
                </div>

                <!-- Address -->
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                        Alamat Laundry <span class="text-red-500">*</span>
                    </label>
                    <textarea 
                        id="address" 
                        name="address" 
                        rows="3"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition resize-none" 
                        placeholder="Alamat lengkap laundry Anda"
                        required
                    ><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" 
                        placeholder="Minimal 6 karakter"
                        minlength="6"
                        required
                    >
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                        Konfirmasi Password <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" 
                        placeholder="Ulangi password Anda"
                        minlength="6"
                        required
                    >
                </div>

                <!-- Has Delivery -->
                <div class="flex items-center space-x-3">
                    <input 
                        type="checkbox" 
                        id="has_delivery" 
                        name="has_delivery" 
                        value="1"
                        <?php echo (isset($_POST['has_delivery']) && $_POST['has_delivery']) ? 'checked' : ''; ?>
                        class="checkbox-custom"
                    >
                    <label for="has_delivery" class="text-sm text-gray-700 cursor-pointer">
                        Menyediakan layanan antar-jemput
                    </label>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="w-full bg-purple-500 hover:bg-purple-600 text-white font-semibold py-3 px-6 rounded-lg btn transition duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5"
                >
                    Daftar Sekarang
                </button>

                <!-- Login Link -->
                <div class="text-center mt-4">
                    <p class="text-sm text-gray-600">
                        Sudah punya akun? 
                        <a href="login.php" class="text-purple-500 hover:text-purple-600 font-medium transition">
                            Login di sini
                        </a>
                    </p>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-xs text-gray-600">
                &copy; <?php echo date('Y'); ?> MICU. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Password tidak cocok');
            } else {
                this.setCustomValidity('');
            }
        });

        // Phone number validation (Indonesia format)
        document.getElementById('phone').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            this.value = value;
            
            if (value.length > 0 && !value.startsWith('0')) {
                this.setCustomValidity('Nomor telepon harus dimulai dengan 0');
            } else if (value.length < 10 || value.length > 13) {
                this.setCustomValidity('Nomor telepon harus 10-13 digit');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>