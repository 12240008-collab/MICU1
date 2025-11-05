<?php
/**
 * Script untuk reset password sample accounts
 * Jalankan file ini sekali untuk memastikan password hash benar
 * Akses: http://localhost/micu/reset_password.php
 */

require_once 'config.php';

$db = db();

// Password yang akan digunakan: password123
$password = 'password123';
$password_hash = password_hash($password, PASSWORD_DEFAULT);

echo "<html><head><title>Reset Password</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:15px;margin:10px 0;border-radius:5px;}";
echo ".info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;margin:10px 0;border-radius:5px;}";
echo ".error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;margin:10px 0;border-radius:5px;}";
echo "</style></head><body>";

echo "<h1>🔐 Reset Password Sample Accounts</h1>";

try {
    // Check if accounts exist
    echo "<h2>📊 Checking Existing Accounts...</h2>";
    
    $stmt = $db->query("SELECT id, full_name, email, user_type FROM users");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<div class='error'><strong>❌ Error:</strong> Tidak ada user di database. Jalankan database.sql terlebih dahulu!</div>";
    } else {
        echo "<div class='info'><strong>✓ Found " . count($users) . " users:</strong><br>";
        foreach ($users as $user) {
            echo "- {$user['full_name']} ({$user['email']}) - Type: {$user['user_type']}<br>";
        }
        echo "</div>";
    }
    
    // Reset passwords
    echo "<h2>🔄 Resetting Passwords...</h2>";
    
    $stmt = $db->prepare("UPDATE users SET password_hash = ?");
    $stmt->execute([$password_hash]);
    
    echo "<div class='success'><strong>✓ Success!</strong> Semua password berhasil direset ke: <strong>password123</strong></div>";
    
    // Display login credentials
    echo "<h2>🔑 Login Credentials:</h2>";
    
    $stmt = $db->query("SELECT full_name, email, user_type FROM users ORDER BY user_type, id");
    $users = $stmt->fetchAll();
    
    echo "<div class='info'>";
    echo "<h3>Customer Accounts:</h3>";
    foreach ($users as $user) {
        if ($user['user_type'] === 'customer') {
            echo "<p><strong>Email:</strong> {$user['email']}<br>";
            echo "<strong>Password:</strong> password123<br>";
            echo "<strong>Name:</strong> {$user['full_name']}</p>";
        }
    }
    
    echo "<h3>Partner Accounts:</h3>";
    foreach ($users as $user) {
        if ($user['user_type'] === 'partner') {
            echo "<p><strong>Email:</strong> {$user['email']}<br>";
            echo "<strong>Password:</strong> password123<br>";
            echo "<strong>Name:</strong> {$user['full_name']}</p>";
        }
    }
    echo "</div>";
    
    // Test password verification
    echo "<h2>🧪 Testing Password Verification...</h2>";
    echo "<div class='info'>";
    
    $test_email = 'cleanfresh@example.com'; // Partner account
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$test_email]);
    $test_user = $stmt->fetch();
    
    if ($test_user) {
        $verify = password_verify('password123', $test_user['password_hash']);
        if ($verify) {
            echo "<p><strong>✓ Verification Test PASSED</strong><br>";
            echo "Password untuk {$test_email} bisa diverifikasi dengan benar.</p>";
        } else {
            echo "<p><strong>❌ Verification Test FAILED</strong><br>";
            echo "Password untuk {$test_email} tidak bisa diverifikasi!</p>";
        }
    }
    echo "</div>";
    
    // Links
    echo "<h2>🔗 Quick Links:</h2>";
    echo "<div class='info'>";
    echo "<p><a href='login.php' style='color:#007bff;text-decoration:none;'>→ Login Customer</a></p>";
    echo "<p><a href='partner/login.php' style='color:#007bff;text-decoration:none;'>→ Login Partner</a></p>";
    echo "<p><a href='index.php' style='color:#007bff;text-decoration:none;'>→ Homepage</a></p>";
    echo "</div>";
    
    echo "<div class='success'><strong>✓ Done!</strong> Silakan coba login sekarang.</div>";
    
    echo "<p style='color:#666;font-size:12px;margin-top:30px;'><em>⚠️ Hapus file ini setelah selesai digunakan untuk keamanan.</em></p>";
    
} catch (Exception $e) {
    echo "<div class='error'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>