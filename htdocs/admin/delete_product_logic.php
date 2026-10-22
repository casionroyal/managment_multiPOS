<?php
session_start();
require '../config/db.php';

// 1. Sécurité : Vérification du rôle Superadmin uniquement
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: products.php?error=unauthorized");
    exit();
}

// 2. Vérification de l'ID du produit
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);

    try {
        // Démarrer une transaction pour garantir l'intégrité des données
        $pdo->beginTransaction();

        // Étape A : Supprimer les entrées de ce produit dans TOUTES les boutiques (Inventaire)
        // C'est nécessaire car inventory.product_id dépend de products.id
        $stmtInv = $pdo->prepare("DELETE FROM inventory WHERE product_id = ?");
        $stmtInv->execute([$id]);

        // Étape B : Supprimer le produit lui-même du catalogue
        $stmtProd = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmtProd->execute([$id]);

        // Si tout est ok, on valide les changements
        $pdo->commit();

        // Redirection avec un message de succès
        header("Location: products.php?msg=deleted_success");
        exit();

    } catch (Exception $e) {
        // En cas d'erreur (ex: produit lié à une vente existante), on annule tout
        $pdo->rollBack();
        
        // On redirige avec un message d'erreur pour ne pas bloquer l'utilisateur
        header("Location: products.php?error=db_error&details=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Si pas d'ID, retour direct
    header("Location: products.php");
    exit();
}