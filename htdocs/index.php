<?php
session_start();

// Si l'utilisateur est déjà connecté, on l'envoie vers l'interface de vente (POS)
if (isset($_SESSION['user_id'])) {
    header("Location: store/pos.php");
    exit();
} 
// Sinon, on le redirige vers la page de connexion
else {
    header("Location: login_page.php");
    exit();
}
?>