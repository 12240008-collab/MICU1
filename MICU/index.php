<?php
session_start();
require_once 'config.php';

// Check if user is already logged in
if (isLoggedIn()) {
    if ($_SESSION['user_type'] === 'customer') {
        redirect('customer/home.php');
    } else {
        redirect('partner/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MICU - Sistem Manajemen Laundry Multi-Vendor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { border-radius: 12px; }
        .btn { border-radius: 12px; }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Hero Section -->
    <div class="gradient-bg min-h-screen flex items-center justify-center px-4">
        <div class="max-w-6xl mx-auto text-center">
            <!-- Logo -->
            <div class="inline-flex items-center justify-center w-24 h-24 bg-white rounded-full mb-8 shadow-2xl">
                <svg class="w-12 h-12 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                </svg>
            </div>

            <!-- Main Heading -->
            <h1 class="text-5xl md:text-7xl font-bold text-white mb-6">
                Selamat Datang di <br> MICU
            </h1>
            <p class="text-xl md:text-2xl text-purple-100 mb-12 max-w-3xl mx-auto">
                Sistem Manajemen Laundry Multi-Vendor yang modern, efisien, dan mudah digunakan
            </p>

            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center mb-16">
                <a href="login.php" class="bg-white hover:bg-gray-100 text-purple-600 font-bold px-10 py-4 btn text-lg shadow-xl transition duration-200 transform hover:scale-105">
                    <svg class="w-6 h-6 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Login Customer
                </a>
                <a href="partner/login.php" class="bg-purple-800 hover:bg-purple-900 text-white font-bold px-10 py-4 btn text-lg shadow-xl transition duration-200 transform hover:scale-105">
                    <svg class="w-6 h-6 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    Login Mitra
                </a>
            </div>

            <!-- Features -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
                <div class="bg-white bg-opacity-10 backdrop-blur-lg card p-6 text-white">
                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Cepat & Mudah</h3>
                    <p class="text-purple-100">Pesan layanan laundry hanya dalam hitungan menit</p>
                </div>

                <div class="bg-white bg-opacity-10 backdrop-blur-lg card p-6 text-white">
                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Tracking Real-time</h3>
                    <p class="text-purple-100">Pantau status cucian Anda setiap saat</p>
                </div>

                <div class="bg-white bg-opacity-10 backdrop-blur-lg card p-6 text-white">
                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Aman & Terpercaya</h3>
                    <p class="text-purple-100">Mitra laundry terverifikasi dan berkualitas</p>
                </div>
            </div>

            <!-- Registration Links -->
            <div class="mt-16 text-white">
                <p class="text-lg mb-4">Belum punya akun?</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="register.php" class="text-white hover:text-purple-200 underline font-semibold">
                        Daftar sebagai Customer →
                    </a>
                    <span class="text-purple-300">|</span>
                    <a href="partner/register.php" class="text-white hover:text-purple-200 underline font-semibold">
                        Daftar sebagai Mitra Laundry →
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-8">
        <div class="max-w-6xl mx-auto px-4 text-center">
            <div class="mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-purple-600 rounded-full mb-3">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                    </svg>
                </div>
                <p class="text-xl font-bold text-white mb-2">MICU</p>
                <p class="text-sm">Sistem Manajemen Laundry Multi-Vendor</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div>
                    <h4 class="text-white font-semibold mb-3">Customer</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="login.php" class="hover:text-white">Login</a></li>
                        <li><a href="register.php" class="hover:text-white">Daftar</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-3">Mitra</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="partner/login.php" class="hover:text-white">Login Mitra</a></li>
                        <li><a href="partner/register.php" class="hover:text-white">Daftar Mitra</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-3">Bantuan</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white">FAQ</a></li>
                        <li><a href="#" class="hover:text-white">Kontak Kami</a></li>
                        <li><a href="#" class="hover:text-white">Syarat & Ketentuan</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-800 pt-6">
                <p class="text-sm">&copy; 2025 MICU. All rights reserved.</p>
                <p class="text-xs mt-2">Made with ❤️ for better laundry management</p>
            </div>
        </div>
    </footer>
</body>
</html>