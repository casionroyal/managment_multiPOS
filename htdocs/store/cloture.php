<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['store_id'])) { header("Location: ../login_page.php"); exit(); }

$store_id = $_SESSION['store_id'];
$today = date('Y-m-d');

// Calculer le total des ventes de la journée pour cette boutique
$stmt = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE store_id = ? AND DATE(created_at) = ?");
$stmt->execute([$store_id, $today]);
$total_systeme = $stmt->fetchColumn() ?: 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Clôture de Caisse (Z) - DZD</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; display: flex; justify-content: center; padding: 50px; }
        .cloture-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.1); width: 400px; }
        .field { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #666; }
        input { width: 100%; padding: 10px; font-size: 1.2em; border: 1px solid #ddd; box-sizing: border-box; }
        .btn-valider { background: #e67e22; color: white; border: none; padding: 15px; width: 100%; font-weight: bold; cursor: pointer; }
        .info-systeme { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>

<div class="cloture-box">
    <h2>Rapport de Clôture caisse</h2>
    <p>Date : <b><?= date('d/m/Y') ?></b></p>
    
    <div class="info-systeme">
        <label>Ventes enregistrées (Système) :</label>
        <div style="font-size: 1.5em; font-weight: bold;"><?= number_format($total_systeme, 2, ',', ' ') ?> DZD</div>
    </div>

    <form action="process_cloture.php" method="POST">
        <input type="hidden" name="total_expected" value="<?= $total_systeme ?>">
        
        <div class="field">
            <label>Montant réel compté en caisse (DZD) :</label>
            <input type="number" step="0.01" name="total_counted" required autofocus>
        </div>

        <button type="submit" class="btn-valider">VALIDER LA CLÔTURE ET DÉCONNECTER</button>
    </form>
    <br>
    <a href="pos.php" style="display:block; text-align:center; color:#7f8c8d;">Annuler et retour à la caisse</a>
</div>

</body>
</html>