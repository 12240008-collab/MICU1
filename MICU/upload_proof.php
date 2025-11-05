<?php
session_start();
require_once 'config.php';
requireCustomer();

$user = getCurrentUser();
$db = db();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    
    // Verify order belongs to customer
    $stmt = $db->prepare("
        SELECT o.*, p.id as payment_id, p.status as payment_status
        FROM orders o
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.id = ? AND o.customer_id = ?
    ");
    $stmt->execute([$order_id, $user['id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $_SESSION['error'] = 'Pesanan tidak ditemukan';
        redirect('customer/orders.php');
    }
    
    // Check if file is uploaded
    if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Silakan pilih file bukti pembayaran';
    } else {
        $file = $_FILES['payment_proof'];
        
        // Validate file
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
        $max_size = MAX_FILE_SIZE; // 5MB
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($file_type, $allowed_types)) {
            $error = 'Tipe file tidak diizinkan. Hanya JPG, PNG, GIF, atau PDF yang diperbolehkan.';
        } 
        // Check file size
        elseif ($file['size'] > $max_size) {
            $error = 'Ukuran file terlalu besar. Maksimal 5MB.';
        }
        // Check for upload errors
        elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Terjadi kesalahan saat upload file.';
        }
        else {
            // Create uploads directory if not exists
            $upload_dir = UPLOAD_DIR . 'payment_proofs/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'proof_' . $order_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Update payment record
                if ($order['payment_id']) {
                    // Update existing payment
                    $stmt = $db->prepare("
                        UPDATE payments 
                        SET payment_proof = ?, status = 'pending'
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_filename, $order['payment_id']]);
                } else {
                    // Create new payment record
                    $stmt = $db->prepare("
                        INSERT INTO payments (order_id, payment_method, amount, status, payment_proof)
                        VALUES (?, 'bank_transfer', ?, 'pending', ?)
                    ");
                    $stmt->execute([$order_id, $order['total_amount'], $new_filename]);
                }
                
                // Send notification to partner
                $stmt = $db->prepare("SELECT user_id FROM laundry_partners WHERE id = ?");
                $stmt->execute([$order['partner_id']]);
                $partner_user = $stmt->fetch();
                
                if ($partner_user) {
                    sendNotification(
                        $partner_user['user_id'],
                        'Bukti Pembayaran Diterima',
                        'Customer telah upload bukti pembayaran untuk pesanan ' . $order['order_number'] . '. Silakan verifikasi.',
                        'payment',
                        '../partner/order-detail.php?id=' . $order_id
                    );
                }
                
                // Send notification to customer
                sendNotification(
                    $user['id'],
                    'Bukti Pembayaran Berhasil Diupload',
                    'Bukti pembayaran Anda untuk pesanan ' . $order['order_number'] . ' sedang diverifikasi oleh mitra.',
                    'payment',
                    'customer/tracking.php?order_id=' . $order_id
                );
                
                $success = 'Bukti pembayaran berhasil diupload dan sedang diverifikasi.';
                
                // Redirect after 2 seconds
                header("refresh:2;url=" . APP_URL . "/customer/payment.php?order_id=" . $order_id);
            } else {
                $error = 'Gagal mengupload file. Silakan coba lagi.';
            }
        }
    }
}

// If accessed via GET, redirect to orders
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('customer/orders.php');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Bukti Pembayaran - MICU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { border-radius: 12px; }
        .btn { border-radius: 12px; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-lg mb-4 text-center">
                <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="font-semibold mb-2">Upload Gagal</p>
                <p class="text-sm"><?php echo $error; ?></p>
                <a href="<?php echo APP_URL; ?>/customer/payment.php?order_id=<?php echo $order_id; ?>" 
                   class="inline-block mt-4 bg-red-500 hover:bg-red-600 text-white font-semibold px-6 py-2 btn transition duration-200">
                    Coba Lagi
                </a>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-lg text-center">
                <svg class="w-16 h-16 mx-auto mb-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="font-semibold text-lg mb-2">Upload Berhasil!</p>
                <p class="text-sm mb-4"><?php echo $success; ?></p>
                <div class="flex items-center justify-center space-x-2 text-sm">
                    <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>Mengalihkan ke halaman pembayaran...</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>