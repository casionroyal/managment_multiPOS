<?php
session_start();
require '../config/db.php';

if ($_SESSION['role'] !== 'superadmin') exit;

$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);

if ($action === 'rename' && $id > 0) {
    $name = $_POST['name'];
    $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
    $stmt->execute([$name, $id]);
    echo json_encode(['status' => 'success']);
}

if ($action === 'delete' && $id > 0) {
    // Vérifier si des produits utilisent cette catégorie
    $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Impossible : des produits utilisent encore cette catégorie !']);
    } else {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success']);
    }
}