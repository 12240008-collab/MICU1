<?php
session_start();
require_once '../config.php';
require_once '../helpers.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$customer_id = $_SESSION['user_id'];

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id']);
    $partner_id = intval($_POST['partner_id']);
    $rating = intval($_POST['rating']);
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    // Sanitize comment
    $comment = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error_message'] = 'Rating harus antara 1-5';
        header('Location: pesanan.php');
        exit;
    }
    
    // Verify order belongs to user and is completed
    $verifyQuery = $conn->query("
        SELECT id FROM orders 
        WHERE id = $order_id 
        AND customer_id = $customer_id 
        AND status = 'done'
    ");
    
    if ($verifyQuery->num_rows === 0) {
        $_SESSION['error_message'] = 'Pesanan tidak valid atau belum selesai';
        header('Location: pesanan.php');
        exit;
    }
    
    // Check if review already exists
    $checkReview = $conn->query("
        SELECT id FROM reviews 
        WHERE order_id = $order_id 
        AND customer_id = $customer_id
    ");
    
    if ($checkReview->num_rows > 0) {
        // Update existing review
        $stmt = $conn->prepare("
            UPDATE reviews 
            SET rating = ?, comment = ?, updated_at = NOW()
            WHERE order_id = ? AND customer_id = ?
        ");
        $stmt->bind_param("isii", $rating, $comment, $order_id, $customer_id);
        
        if ($stmt->execute()) {
            // Recalculate partner rating
            updatePartnerRating($conn, $partner_id);
            
            $_SESSION['success_message'] = '⭐ Rating berhasil diperbarui!';
        } else {
            $_SESSION['error_message'] = '❌ Gagal memperbarui rating';
        }
        $stmt->close();
    } else {
        // Insert new review
        $stmt = $conn->prepare("
            INSERT INTO reviews (order_id, customer_id, partner_id, rating, comment)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiiis", $order_id, $customer_id, $partner_id, $rating, $comment);
        
        if ($stmt->execute()) {
            // Update partner rating
            updatePartnerRating($conn, $partner_id);
            
            // Send notification to partner
            $customerQuery = $conn->query("SELECT full_name FROM users WHERE id = $customer_id");
            $customer = $customerQuery->fetch_assoc();
            
            $partnerQuery = $conn->query("SELECT user_id FROM laundry_partners WHERE id = $partner_id");
            $partner = $partnerQuery->fetch_assoc();
            
            if ($partner) {
                $notifTitle = "Rating Baru dari Pelanggan";
                $notifMessage = $customer['full_name'] . " memberikan rating " . $rating . " bintang untuk layanan Anda";
                $notifMessage = $conn->real_escape_string($notifMessage);
                
                $conn->query("
                    INSERT INTO notifications (user_id, title, message, type, link)
                    VALUES ({$partner['user_id']}, '$notifTitle', '$notifMessage', 'order', '../partner/reviews.php')
                ");
            }
            
            $_SESSION['success_message'] = '🌟 Terima kasih atas rating Anda!';
        } else {
            $_SESSION['error_message'] = '❌ Gagal mengirim rating: ' . $stmt->error;
        }
        $stmt->close();
    }
} else {
    $_SESSION['error_message'] = 'Method tidak valid';
}

// Function to update partner rating
function updatePartnerRating($conn, $partner_id) {
    $ratingQuery = $conn->query("
        SELECT 
            COUNT(*) as total_reviews,
            AVG(rating) as avg_rating
        FROM reviews
        WHERE partner_id = $partner_id
    ");
    
    if ($ratingQuery) {
        $result = $ratingQuery->fetch_assoc();
        $total_reviews = intval($result['total_reviews']);
        $avg_rating = round(floatval($result['avg_rating']), 2);
        
        $conn->query("
            UPDATE laundry_partners
            SET rating = $avg_rating, total_reviews = $total_reviews
            WHERE id = $partner_id
        ");
    }
}

header('Location: pesanan.php');
exit;
?>