<?php
session_start();
require '../config/db.php';

$sku = $_GET['sku'] ?? '';
$store_id = $_SESSION['store_id'];

// On cherche le produit et on vérifie le stock local (MCD)
$stmt = $pdo->prepare("
    SELECT p.*, i.quantity 
    FROM products p
    JOIN inventory i ON p.id = i.product_id
    WHERE p.sku = ? AND i.store_id = ?
");
$stmt->execute([$sku, $store_id]);
$product = $stmt->fetch();

if ($product) {
    echo json_encode($product);
} else {
    echo json_encode(['error' => 'Not found']);
}