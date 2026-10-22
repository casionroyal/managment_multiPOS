<?php
session_start();
require 'config/db.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nettoyage des entrées
    $username = strtolower(trim($_POST['username']));
    $password = $_POST['password'];

    // 1. Recherche de l'utilisateur actif avec JOINTURE pour avoir le nom de la boutique
    $query = "
        SELECT u.*, s.name as shop_name 
        FROM users u 
        LEFT JOIN stores s ON u.store_id = s.id 
        WHERE LOWER(u.username) = ? AND u.is_active = 1 
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // 2. Vérification de l'existence et du mot de passe
    if ($user && password_verify($password, $user['password'])) {
        
        // Sécurité : Régénération de l'ID de session
        session_regenerate_id(true);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['store_id']   = $user['store_id'];
        
        // ON STOCK LE NOM DE LA BOUTIQUE EN SESSION
        // Si l'admin n'a pas de boutique, on met "Administration Centrale"
        $_SESSION['store_name'] = $user['shop_name'] ?? 'Administration Centrale';

        // --- REDIRECTION ---
        if ($user['role'] === 'superadmin' || $user['role'] === 'superviseur') {
            header("Location: admin/dashboard.php");
        } elseif ($user['role'] === 'vendeur') {
            header("Location: store/pos.php");
        } else {
            header("Location: login_page.php?error=role_inconnu");
        }
        exit();
        
    } else {
        header("Location: login_page.php?error=1");
        exit();
    }
} else {
    header("Location: login_page.php");
    exit();
}