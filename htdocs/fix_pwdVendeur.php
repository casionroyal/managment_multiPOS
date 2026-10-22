<?php
require 'config/db.php';

// Remplace 'NOM_DU_VENDEUR' par le vrai pseudo qui est dans ta base
$username_vendeur = ''; 
$nouveau_pass = ''; 

$hash = password_hash($nouveau_pass, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");

if($stmt->execute([$hash, $username_vendeur])) {
    echo "✅ Le compte vendeur <b>$username_vendeur</b> est maintenant haché et sécurisé !";
    echo "<a href='login_page.php'>Aller au login</a>";
} else {
    echo "❌ Erreur : Utilisateur non trouvé.";
}
?>