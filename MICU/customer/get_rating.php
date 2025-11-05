<?php
// filepath: d:\Software\xampp\htdocs\MICU\customer\get_rating.php
session_start();
require_once '../config.php';
require_once '../helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$order_id = intval($_GET['order_id'] ?? 0);
if (!$order_id) {
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
}

// Get existing rating
$stmt = $conn->prepare("
    SELECT rating, comment, created_at 
    FROM partner_ratings 
    WHERE order_id = ? AND customer_id = ?
");

$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $rating = $result->fetch_assoc();
    echo json_encode([
        'hasRating' => true,
        'rating' => $rating['rating'],
        'comment' => $rating['comment'],
        'created_at' => $rating['created_at']
    ]);
} else {
    echo json_encode(['hasRating' => false]);
}