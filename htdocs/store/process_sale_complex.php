<?php
session_start();
require '../config/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// Sécurité : Vérification panier et session
if (!$data || empty($data['cart']) || !isset($_SESSION['store_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Panier vide ou session expirée']);
    exit;
}

$store_id = $_SESSION['store_id'];
$user_id = $_SESSION['user_id'];
$total_amount = 0;

try {
    $pdo->beginTransaction();

    // 1. Préparation des requêtes (Optimisation performance)
    $stmtGetProd   = $pdo->prepare("SELECT purchase_price FROM products WHERE id = ?");
    $stmtCheckStock = $pdo->prepare("SELECT quantity FROM inventory WHERE store_id = ? AND product_id = ?");
    $stmtUpdateStock = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE store_id = ? AND product_id = ?");
    $stmtInsertItem  = $pdo->prepare("INSERT INTO order_items (order_id, product_id, purchase_price, quantity, unit_price, manual_name) VALUES (?, ?, ?, ?, ?, ?)");

    // 2. Création de la commande principale (orders)
    $stmtOrder = $pdo->prepare("INSERT INTO orders (store_id, user_id, total_amount, payment_method) VALUES (?, ?, 0, 'cash')");
    $stmtOrder->execute([$store_id, $user_id]);
    $order_id = $pdo->lastInsertId();

    foreach ($data['cart'] as $item) {
        $isManual = (strpos((string)$item['id'], 'MANUAL_') === 0);
        $productId = $isManual ? null : $item['id'];
        $purchase_price = 0;
        $manual_name = $isManual ? str_replace("[MANUEL] ", "", $item['name']) : null;
        $qty = floatval($item['qty']);
        $unit_price = floatval($item['base_price']);

        if (!$isManual) {
            // A. Récupérer prix d'achat pour le bénéfice futur
            $stmtGetProd->execute([$productId]);
            $pInfo = $stmtGetProd->fetch();
            $purchase_price = $pInfo['purchase_price'] ?? 0;

            // B. Vérification du stock disponible
            $stmtCheckStock->execute([$store_id, $productId]);
            $current_stock = $stmtCheckStock->fetchColumn();
            
            if ($current_stock < $qty) {
                throw new Exception("Stock insuffisant pour : " . $item['name']);
            }

            // C. Mise à jour du stock
            $stmtUpdateStock->execute([$qty, $store_id, $productId]);
        }

        // 3. Insertion du détail (order_items)
        $stmtInsertItem->execute([
            $order_id, 
            $productId, 
            $purchase_price, 
            $qty, 
            $unit_price, 
            $manual_name
        ]);

        $total_amount += ($unit_price * $qty);
    }

    // 4. Finalisation : Mise à jour du total réel de la commande
    $stmtUpdateOrder = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
    $stmtUpdateOrder->execute([$total_amount, $order_id]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'order_id' => $order_id]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}