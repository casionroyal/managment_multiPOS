<?php
session_start();
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $inventory_id = intval($_POST['inventory_id']);
    $add_qty = floatval($_POST['add_qty']);
    $user_id = $_SESSION['user_id'];
    $user_store_id = $_SESSION['store_id'];

    if ($add_qty <= 0) {
        header("Location: inventory.php?error=qty");
        exit();
    }

    // VERIFICATION DE SÉCURITÉ : Est-ce que cet inventory_id appartient bien à la boutique du superviseur ?
    if ($_SESSION['role'] === 'superviseur') {
        $stmtCheck = $pdo->prepare("SELECT store_id FROM inventory WHERE id = ?");
        $stmtCheck->execute([$inventory_id]);
        $inv = $stmtCheck->fetch();
        
        if (!$inv || $inv['store_id'] != $user_store_id) {
            die("Action non autorisée sur cette boutique.");
        }
    }

    // MISE À JOUR DU STOCK
    $sql = "UPDATE inventory SET quantity = quantity + ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$add_qty, $inventory_id])) {
        header("Location: inventory.php?success=1");
    } else {
        header("Location: inventory.php?error=db");
    }
    exit();
}