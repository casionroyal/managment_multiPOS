<?php
session_start();
require '../config/db.php';

// Sécurité : Seul le superadmin peut supprimer
if ($_SESSION['role'] !== 'superadmin') {
    die("Accès refusé.");
}

// --- LOGIQUE DE SUPPRESSION ---
if (isset($_GET['delete_id'])) {
    $id_a_supprimer = $_GET['delete_id'];

    // On supprime l'utilisateur
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id_a_supprimer]);

    // IMPORTANT : Rediriger immédiatement vers le dashboard après la suppression
    header("Location: dashboard.php?msg=user_deleted");
    exit(); // Toujours mettre exit() après une redirection
}

// --- LOGIQUE D'ENREGISTREMENT / MODIFICATION (Ton code existant) ---
if (isset($_POST['save_user'])) {
    // ... ton code de création/update ...
    header("Location: dashboard.php?msg=success");
    exit();
}