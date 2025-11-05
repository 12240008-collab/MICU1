<?php
/**
 * helpers.php
 * Berisi fungsi-fungsi bantu untuk order processing dan tampilan.
 */

// --- 1. Fungsi Pembuatan Nomor Order dan Notifikasi (Untuk process_order.php) ---

/**
 * Membuat Nomor Pesanan Unik.
 * Format: MICU-YYYYMMDD-XXXX (Contoh: MICU-20251104-0001)
 * Membutuhkan koneksi database ($conn) sebagai parameter.
 */
if (!function_exists('generateOrderNumber')) {
    function generateOrderNumber($conn) {
        $prefix = 'MICU-' . date('Ymd');
        
        // Ambil nomor urut terakhir hari ini
        $stmt = $conn->prepare("
            SELECT order_number FROM orders 
            WHERE order_number LIKE ? 
            ORDER BY id DESC 
            LIMIT 1
        ");
        
        $search_pattern = $prefix . '-%';
        $stmt->bind_param("s", $search_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        $last_number = 0;
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            // Ambil 4 digit terakhir dari nomor pesanan
            $last_part = substr($row['order_number'], -4); 
            $last_number = intval($last_part);
        }
        
        // Increment dan format menjadi 4 digit
        $new_number = $last_number + 1;
        $suffix = str_pad($new_number, 4, '0', STR_PAD_LEFT);
        
        return $prefix . '-' . $suffix;
    }
}


/**
 * Mencatat notifikasi baru ke database.
 * Membutuhkan variabel $conn yang didefinisikan secara global.
 * @param int $user_id ID pengguna penerima
 * @param string $title Judul notifikasi
 * @param string $content Isi/deskripsi notifikasi
 * @param string $type Tipe notifikasi (misal: order, payment, system)
 * @param string $link Link tujuan saat notifikasi diklik
 */
if (!function_exists('createNotification')) {
    function createNotification($user_id, $title, $content, $type = 'system', $link = '#') {
        // Memastikan koneksi database global tersedia
        global $conn;
        
        if (!isset($conn) || $conn->connect_error) {
            return false;
        }

        try {
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, content, type, link)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param("issss", 
                $user_id, 
                $title, 
                $content, 
                $type, 
                $link
            );
            
            $stmt->execute();
            $stmt->close();
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
}


// --- 2. Fungsi Status Order (Kode Anda) ---

// Mengembalikan status "logis" yang digunakan sistem
function getDisplayStatus($status) {
    // Status yang digunakan sekarang
    $valid = ['pending','accepted','picked_up','processing','delivering','completed','cancelled'];
    if (!in_array($status, $valid)) {
        // fallback
        return 'processing';
    }
    return $status;
}

// Label yang ditampilkan ke user (Bahasa Indonesia)
function displayLabel($status) {
    $labels = [
        'pending'    => 'Menunggu Konfirmasi',
        'accepted'   => 'Pesanan Diterima',
        'picked_up'  => 'Menunggu Driver Menjemput Laundry-an',
        'processing' => 'Diproses',
        'delivering' => 'Laundry Diantar',
        'completed'  => 'Selesai',
        'cancelled'  => 'Dibatalkan'
    ];
    return $labels[$status] ?? ucfirst($status);
}

// --- 3. Fungsi Perhitungan Jarak (Kode Anda) ---

if (!function_exists('calculateDistance')) {
    function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        if ($lat1 == null || $lon1 == null || $lat2 == null || $lon2 == null) {
            return null;
        }
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return round($earthRadius * $c, 1);
    }
}
?>