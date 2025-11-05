<?php
session_start();
require_once '../config.php';

// Redirect if already logged in
if (isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'partner') {
    redirect('partner/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi';
    } else {
        $db = db();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND user_type = 'partner'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'Email tidak terdaftar sebagai mitra laundry';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Password salah';
        } else {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['language'] = $user['language'];
            
            // Get partner info
            $stmt = $db->prepare("SELECT id, laundry_name FROM laundry_partners WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $partner = $stmt->fetch();
            
            if ($partner) {
                $_SESSION['partner_id'] = $partner['id'];
                $_SESSION['laundry_name'] = $partner['laundry_name'];
            }
            
            redirect('partner/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Mitra - MICU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { border-radius: 12px; }
        .btn { border-radius: 12px; }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-50 to-purple-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-purple-500 rounded-full mb-4">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-800">MICU Partner</h1>
            <p class="text-gray-600">Portal Mitra Laundry</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white card shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Login Mitra</h2>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Email</label>
                    <input type="email" name="email" required 
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        placeholder="mitra@email.com">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                    <input type="password" name="password" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        placeholder="••••••••">
                </div>

                <div class="flex items-center justify-between mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="remember" class="w-4 h-4 text-purple-500 border-gray-300 rounded focus:ring-purple-500">
                        <span class="ml-2 text-sm text-gray-600">Ingat Saya</span>
                    </label>
                    <a href="#" class="text-sm text-purple-500 hover:text-purple-600">Lupa Password?</a>
                </div>

                <button type="submit" class="w-full bg-purple-500 hover:bg-purple-600 text-white font-semibold py-3 btn transition duration-200">
                    Masuk
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-gray-600 text-sm">
                    Belum jadi mitra? 
                    <a href="register.php" class="text-purple-500 hover:text-purple-600 font-semibold">Daftar Sekarang</a>
                </p>
            </div>

            <div class="mt-4 text-center">
                <a href="../login.php" class="text-sm text-gray-500 hover:text-gray-700">
                    ← Kembali ke Login Customer
                </a>
            </div>
        </div>

        <div class="text-center mt-6 text-sm text-gray-600">
            © 2025 MICU. All rights reserved.
        </div>
    </div>
</body>
</html>