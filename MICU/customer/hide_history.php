<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
  $order_id = intval($_POST['order_id']);
  $user_id = intval($_SESSION['user_id']);

  $sql = "UPDATE orders 
          SET hidden_by_customer = 1 
          WHERE id = $order_id AND customer_id = $user_id";
  
  if ($conn->query($sql)) {
    echo "<script>alert('Riwayat pesanan berhasil dihapus dari tampilan Anda.');window.location='history.php';</script>";
  } else {
    echo "<script>alert('Terjadi kesalahan, coba lagi.');window.location='history.php';</script>";
  }
} else {
  header('Location: history.php');
  exit;
}
