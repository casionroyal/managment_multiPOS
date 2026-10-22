<?php
session_start();
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $sku = $_POST['sku'];
    $cat_id = $_POST['category_id'];
    $store_id = $_POST['store_id'];
    
    // Conversion en nombres décimaux
    $p_price = floatval($_POST['purchase_price']);
    $s_price = floatval($_POST['selling_price']);
    $initial_qty = floatval($_POST['initial_qty']);

    // VERIFICATION SERVEUR
    if ($p_price <= 0) {
        header("Location: products.php?error=price_required");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Créer le produit avec le prix d'achat
        $stmtProd = $pdo->prepare("INSERT INTO products (name, sku, category_id, purchase_price, selling_price) VALUES (?, ?, ?, ?, ?)");
        $stmtProd->execute([$name, $sku, $cat_id, $p_price, $s_price]);
        $new_id = $pdo->lastInsertId();

        // 2. Créer l'inventaire
        $stmtInv = $pdo->prepare("INSERT INTO inventory (product_id, store_id, quantity, min_threshold) VALUES (?, ?, ?, ?)");
        $stmtInv->execute([$new_id, $store_id, $initial_qty, 5]);

        $pdo->commit();
        header("Location: products.php?success=1");
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur : " . $e->getMessage());
    }
}