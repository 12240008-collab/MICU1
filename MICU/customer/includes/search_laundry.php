<?php
// includes/search_laundry.php
session_start();
require_once '../../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

// Escape query for LIKE search
$searchTerm = '%' . $conn->real_escape_string($query) . '%';

// Search laundry partners by name or address
$sql = "
    SELECT 
        lp.id,
        lp.laundry_name,
        lp.laundry_address,
        lp.rating,
        lp.total_reviews,
        lp.has_pickup_delivery
    FROM laundry_partners lp
    WHERE lp.is_active = 1
    AND (
        lp.laundry_name LIKE ?
        OR lp.laundry_address LIKE ?
    )
    ORDER BY 
        lp.rating DESC,
        lp.total_reviews DESC
    LIMIT 10
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$results = [];
while ($row = $result->fetch_assoc()) {
    $results[] = $row;
}

echo json_encode($results);