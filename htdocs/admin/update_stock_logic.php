<?php
session_start();
require '../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Données invalides']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Si l'utilisateur est Superadmin, on met à jour les prix dans la table 'products'
    if ($_SESSION['role'] === 'superadmin' && $data['purchase_price'] !== null) {
        $stmtPrice = $pdo->prepare("UPDATE products SET purchase_price = ?, selling_price = ? WHERE id = ?");
        $stmtPrice->execute([$data['purchase_price'], $data['selling_price'], $data['product_id']]);
    }

    // 2. On met à jour le stock dans la table 'inventory' (Ajout de quantité)
    if ($data['added_qty'] != 0) {
        $stmtStock = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE product_id = ? AND store_id = ?");
        $stmtStock->execute([$data['added_qty'], $data['product_id'], $data['store_id']]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}