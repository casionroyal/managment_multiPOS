<?php
session_start();
require '../config/db.php';

// Sécurité : Seul le superadmin peut manipuler les boutiques
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    exit("Accès refusé");
}

// --- AJOUTER UNE BOUTIQUE ---
if (isset($_POST['add_store'])) {
    $name = trim($_POST['store_name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO stores (name) VALUES (?)");
        $stmt->execute([$name]);
    }
}

// --- SUPPRIMER UNE BOUTIQUE ---
// Note : On utilise 'delete_store' pour correspondre au lien de la modale
if (isset($_GET['delete_store'])) {
    $id = $_GET['delete_store'];
    
    // Optionnel : Vérifier si la boutique est vide avant de supprimer
    // $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE store_id = ?");
    // $check->execute([$id]);
    // if($check->fetchColumn() == 0) { ... }

    $stmt = $pdo->prepare("DELETE FROM stores WHERE id = ?");
    $stmt->execute([$id]);
}

// --- RENOMMER UNE BOUTIQUE ---
if (isset($_POST['rename_store'])) {
    $id = $_POST['store_id'];
    $new_name = trim($_POST['new_name']);
    
    if (!empty($new_name) && !empty($id)) {
        $stmt = $pdo->prepare("UPDATE stores SET name = ? WHERE id = ?");
        $stmt->execute([$new_name, $id]);
    }
}

header("Location: dashboard.php?msg=config_updated");
exit();