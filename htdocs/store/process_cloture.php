<?php
session_start();
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['store_id'])) {
    $store_id = $_SESSION['store_id'];
    $user_id = $_SESSION['user_id'];
    $expected = $_POST['total_expected'];
    $counted = $_POST['total_counted'];
    $difference = $counted - $expected;
    $today = date('Y-m-d');

    $sql = "INSERT INTO daily_closings (store_id, user_id, closing_date, total_expected, total_counted, difference) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$store_id, $user_id, $today, $expected, $counted, $difference])) {
        // Après la clôture, on déconnecte l'utilisateur par sécurité
        session_destroy();
        header("Location: ../login_page.php?msg=Clôture effectuée avec succès");
    } else {
        echo "Erreur lors de la clôture.";
    }
}