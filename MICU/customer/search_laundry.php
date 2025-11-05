<?php
// File: customer/search_laundry.php
require_once '../config.php';
require_once '../helpers.php';

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        id,
        laundry_name,
        laundry_address,
        image_url
    FROM laundry_partners 
    WHERE is_active = 1 
    AND (laundry_name LIKE ? OR laundry_address LIKE ?)
    ORDER BY 
        CASE WHEN laundry_name LIKE ? THEN 1 ELSE 2 END,
        rating DESC
    LIMIT 6
");

$searchTerm = "%$query%";
$stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$laundries = [];
while ($row = $result->fetch_assoc()) {
    $laundries[] = $row;
}

echo json_encode($laundries);
?>