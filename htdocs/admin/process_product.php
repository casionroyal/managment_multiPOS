<?php
session_start();
require '../config/db.php';

// Sécurité : Seul l'admin ou le superviseur (si tu l'autorises) crée des produits
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'superviseur'])) {
    die("Accès refusé");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    
    // Récupération des données du formulaire
    $name = trim($_POST['name']);
    $variant = trim($_POST['variant']); // ex: Rouge / XL / Amandes
    $unit_type = $_POST['unit_type'];   // unite, kg, litre
    $sku = trim($_POST['sku']);         // Code barre
    $purchase_price = floatval($_POST['purchase_price']);

        // Sécurité supplémentaire : On arrête tout si le prix d'achat est 0 ou vide
        if ($purchase_price <= 0) {
            die("Erreur critique : Le prix d'achat doit être supérieur à 0 pour le calcul de rentabilité.");
        }
    $selling_price = floatval($_POST['selling_price']);
    $min_threshold = intval($_POST['min_threshold'] ?? 5);
    $category_id = intval($_POST['category_id']);

    try {
        $pdo->beginTransaction();

        // 1. AJOUT DANS LE CATALOGUE GLOBAL (Table products)
        $sqlProd = "INSERT INTO products (name, variant, unit_type, sku, purchase_price, selling_price, category_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmtProd = $pdo->prepare($sqlProd);
        $stmtProd->execute([$name, $variant, $unit_type, $sku, $purchase_price, $selling_price, $category_id]);
        
        $new_product_id = $pdo->lastInsertId();

        // 2. DISPATCHING DANS TOUTES LES BOUTIQUES (Table inventory)
        // On récupère toutes les boutiques existantes
        $stores = $pdo->query("SELECT id FROM stores")->fetchAll();
        
        $sqlInv = "INSERT INTO inventory (store_id, product_id, quantity, min_threshold) VALUES (?, ?, 0, ?)";
        $stmtInv = $pdo->prepare($sqlInv);
        
        foreach ($stores as $store) {
            // On initialise le stock à 0 pour chaque boutique
            $stmtInv->execute([$store['id'], $new_product_id, $min_threshold]);
        }

        $pdo->commit();
        header("Location: products.php?success=1");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur critique : " . $e->getMessage());
    }
}