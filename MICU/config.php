<?php
/**
 * MICU - Configuration File
 * Database and Application Settings
 */
// Di bagian awal config.php, pastikan seperti ini:
$host = "localhost";
$user = "root";
$pass = "";  // Kosongkan jika tidak ada password
$dbname = "micu_laundry";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Base URL (ganti sesuai domain atau localhost kamu)
$base_url = "http://localhost/MICU/";

// Website branding
$config = [
    "site_name"    => "MICU Laundry",
    "site_tagline" => "Laundry Cepat, Bersih, dan Wangi!",

    // ✅ logo & favicon bisa langsung pakai link URL (CDN, imgur, Cloudinary, dll)
    "logo_url"     => "https://cdn.discordapp.com/attachments/1420682370671181902/1432666495745392682/LOGO_MICU_2.png?ex=6901e226&is=690090a6&hm=715dceceae446ebe7b052c64c3ce14462d0aa9852bb68cf8da87b25c0c17978f&",  // ubah link sesuai keinginanmu
    "favicon_url"  => "https://i.imgur.com/UGNknbF.png",  // ubah link favicon di sini

    // ✅ Avatar default (kalau user belum upload)
    // Bisa juga pakai URL dari CDN seperti Imgur, Cloudinary, dsb.
    "avatar_default" => "https://cdn.discordapp.com/attachments/1420682370671181902/1432669043835539549/60111.jpg?ex=6901e485&is=69009305&hm=561576fa248360e78cfc65f18ab79d1fb11ea03578a05f6a8adfcf3b9b9b7099&", // contoh avatar default
    "avatar_local"   => "MICU/customer/assets/icons/user-avatar.png",     // fallback lokal

    // default fallback (jika link gagal dimuat)
    "logo_local"   => "MICU/customer/assets/icons/logo.png",
    "favicon_local"=> "MICU/customer/assets/icons/favicon.png",
];

// Single database connection
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "micu_laundry";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Gunakan $conn sebagai koneksi utama
$db = $conn; // alias untuk kompatibilitas

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'micu_laundry');

// Application Settings
define('APP_NAME', 'MICU');
define('APP_URL', 'http://localhost/MICU');
define('TIMEZONE', 'Asia/Jakarta');

// Session Configuration
define('SESSION_LIFETIME', 1800); // 30 minutes

// Upload Settings
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Pagination
define('ITEMS_PER_PAGE', 10);

// Set timezone
date_default_timezone_set(TIMEZONE);

// Database Connection Class
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Helper Functions
function db() {
    return Database::getInstance()->getConnection();
}

function redirect($url) {
    // Handle relative URLs
    if (strpos($url, 'http') !== 0) {
        // Remove leading slash if exists
        $url = ltrim($url, '/');
        $url = APP_URL . '/' . $url;
    }
    header("Location: " . $url);
    exit();
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function formatRupiah($amount) {
    return "Rp " . number_format($amount, 0, ',', '.');
}

function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earthRadius * $c;
    
    return round($distance, 2);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Redirect to appropriate login page based on current path
        $current_path = $_SERVER['REQUEST_URI'];
        if (strpos($current_path, '/partner/') !== false) {
            redirect(APP_URL . '/partner/login.php');
        } else {
            redirect(APP_URL . '/login.php');
        }
    }
}

function requireCustomer() {
    requireLogin();
    if ($_SESSION['user_type'] !== 'customer') {
        // Clear session and redirect to customer login
        session_destroy();
        redirect(APP_URL . '/login.php');
    }
}

function requirePartner() {
    requireLogin();
    if ($_SESSION['user_type'] !== 'partner') {
        // Clear session and redirect to partner login
        session_destroy();
        redirect(APP_URL . '/partner/login.php');
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = db();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function sendNotification($userId, $title, $message, $type = 'system', $link = null) {
    $db = db();
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, link)
        VALUES (?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$userId, $title, $message, $type, $link]);
}

function getUnreadNotifications($userId) {
    $db = db();
    $stmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = FALSE 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getTranslation($key, $lang = 'id') {
    $translations = [
        'id' => [
            'login' => 'Masuk',
            'register' => 'Daftar',
            'logout' => 'Keluar',
            'email' => 'Email',
            'password' => 'Kata Sandi',
            'full_name' => 'Nama Lengkap',
            'phone' => 'Nomor Telepon',
            'address' => 'Alamat',
            'submit' => 'Kirim',
            'cancel' => 'Batal',
            'save' => 'Simpan',
            'edit' => 'Edit',
            'delete' => 'Hapus',
            'back' => 'Kembali',
            'order_now' => 'Pesan Sekarang',
            'track_order' => 'Lacak Pesanan',
            'my_orders' => 'Pesanan Saya',
            'profile' => 'Profil',
            'dashboard' => 'Dashboard',
        ],
        'en' => [
            'login' => 'Login',
            'register' => 'Register',
            'logout' => 'Logout',
            'email' => 'Email',
            'password' => 'Password',
            'full_name' => 'Full Name',
            'phone' => 'Phone Number',
            'address' => 'Address',
            'submit' => 'Submit',
            'cancel' => 'Cancel',
            'save' => 'Save',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'back' => 'Back',
            'order_now' => 'Order Now',
            'track_order' => 'Track Order',
            'my_orders' => 'My Orders',
            'profile' => 'Profile',
            'dashboard' => 'Dashboard',
        ]
    ];
    
    return $translations[$lang][$key] ?? $key;
}

function t($key) {
    $lang = $_SESSION['language'] ?? 'id';
    return getTranslation($key, $lang);
}
?>