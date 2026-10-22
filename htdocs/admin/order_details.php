<?php
session_start();
require '../config/db.php';

// --- MODIFICATION ICI ---
// On autorise à la fois 'superadmin' ET 'superviseur'
$allowed_roles = ['superadmin', 'superviseur'];

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Au lieu d'une page blanche (exit), on redirige vers le login avec un message
    header("Location: ../login_page.php?error=unauthorized");
    exit();
}

// Sécurité : on s'assure que l'ID est bien un nombre entier
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT oi.*, p.name as db_name 
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détails Vente #<?= $order_id ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px; background-color: #f4f7f6; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 800px; margin: auto; }
        h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #3498db; color: white; padding: 12px; text-align: left; }
        td { border-bottom: 1px solid #ddd; padding: 12px; }
        tr:hover { background-color: #f9f9f9; }
        .btn-back { display: inline-block; margin-top: 20px; padding: 10px 15px; background: #95a5a6; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Détails de la vente #<?= $order_id ?></h2>
        <table>
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Quantité</th>
                    <th>Prix Unit.</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="4" style="text-align:center;">Aucun article trouvé pour cette vente.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['db_name'] ?? $item['manual_name'] ?? 'Produit inconnu') ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= number_format($item['unit_price'], 2, ',', ' ') ?> DZD</td>
                        <td><strong><?= number_format($item['quantity'] * $item['unit_price'], 2, ',', ' ') ?> DZD</strong></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <br>
        <a href="dashboard.php" class="btn-back">⬅ Retour au tableau de bord</a>
    </div>
</body>
</html>