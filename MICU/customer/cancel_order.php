<?php
session_start();
require_once '../config.php';
require_once '../helpers.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

$user_id = $_SESSION['user_id'];
$order_id = intval($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
  echo "<script>alert('ID pesanan tidak valid!'); history.back();</script>";
  exit;
}

// Pastikan pesanan milik user dan masih pending + belum dibayar
$orderQuery = $conn->prepare("
  SELECT * FROM orders 
  WHERE id = ? AND customer_id = ? AND status = 'pending' AND payment_status = 'unpaid'
");
$orderQuery->bind_param("ii", $order_id, $user_id);
$orderQuery->execute();
$result = $orderQuery->get_result();

if ($result->num_rows === 0) {
  echo "<script>alert('Pesanan tidak dapat dibatalkan!'); history.back();</script>";
  exit;
}

// Update status menjadi cancelled
$update = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
$update->bind_param("i", $order_id);
$update->execute();

// Tambahkan log tracking
$conn->query("INSERT INTO order_tracking (order_id, status, notes) VALUES ($order_id, 'cancelled', 'Pesanan dibatalkan oleh pelanggan')");

// Redirect ke riwayat dengan notifikasi sukses
header("Location: riwayatpesanan.php?cancel_success=1");
exit;
?>
