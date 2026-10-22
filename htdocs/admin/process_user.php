<?php
session_start();
require '../config/db.php';

// 1. VERIFICATION GENERALE (Seuls Admin et Superviseur entrent)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'superviseur'])) {
    header("Location: dashboard.php?error=unauthorized");
    exit();
}

if (isset($_POST['save_user'])) {
    $user_id  = $_POST['user_id'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // --- LOGIQUE DE RESTRICTION PAR RÔLE ---
    if ($_SESSION['role'] === 'superviseur') {
        // Le superviseur ne peut créer QUE des vendeurs pour SA boutique
        $role = 'vendeur';
        $store_id = $_SESSION['store_id']; 
    } else {
        // Le superadmin, lui, peut tout choisir
        $role = $_POST['role'];
        $store_id = !empty($_POST['store_id']) ? $_POST['store_id'] : null;
    }

    // --- TRAITEMENT SQL ---
    if (!empty($user_id)) {
        // MODIFICATION
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET username=?, password=?, role=?, store_id=? WHERE id=?";
            $params = [$username, $hashed, $role, $store_id, $user_id];
        } else {
            $sql = "UPDATE users SET username=?, role=?, store_id=? WHERE id=?";
            $params = [$username, $role, $store_id, $user_id];
        }
        
        // Sécurité supplémentaire : Un superviseur ne peut modifier que ses propres vendeurs
        if ($_SESSION['role'] === 'superviseur') {
            $sql .= " AND store_id = ?";
            $params[] = $_SESSION['store_id'];
        }
        
    } else {
        // CRÉATION
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, role, store_id, is_active) VALUES (?, ?, ?, ?, 1)";
        $params = [$username, $hashed, $role, $store_id];
    }

    $pdo->prepare($sql)->execute($params);
    header("Location: dashboard.php?msg=success");
    exit();
}

header("Location: dashboard.php");
exit();