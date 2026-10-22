<?php
require '../config/db.php';
$order_id = $_GET['id'];

// Récupérer les infos de la vente
$stmt = $pdo->prepare("SELECT o.*, s.name as store_name FROM orders o JOIN stores s ON o.store_id = s.id WHERE o.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

// Récupérer les articles
$stmtItems = $pdo->prepare("SELECT oi.*, p.name as db_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Courier New', monospace; width: 80mm; margin: 0; padding: 5px; font-size: 12px; }
        .text-center { text-align: center; }
        .header { margin-bottom: 10px; border-bottom: 1px dashed #000; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; }
        .total { font-size: 1.4em; font-weight: bold; margin-top: 10px; border-top: 1px double #000; padding-top: 5px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print();"> <div class="header text-center">
        <h2 style="margin:0;"><?= htmlspecialchars($order['store_name']) ?></h2>
        <p>Vente #<?= $order_id ?><br><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Désignation</th>
                <th>Qté</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?= $item['db_name'] ?? $item['manual_name'] ?></td>
                <td>x<?= $item['quantity'] ?></td>
                <td style="text-align:right;"><?= number_format($item['quantity'] * $item['unit_price'], 2, ',', ' ') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total text-center">
        TOTAL : <?= number_format($order['total_amount'], 2, ',', ' ') ?> DZD
    </div>

    <div class="text-center" style="margin-top:15px;">
        <p>Merci de votre visite !</p>
    </div>

    <button class="no-print" onclick="window.close()" style="margin-top:20px; width:100%;">Fermer la fenêtre</button>

</body>

            <!--   Declencheur d'impression -->

     <script>
            // Lance l'impression dès que la page du ticket est chargée
            window.onload = function() {
                window.print();
                // Optionnel : ferme la fenêtre automatiquement après l'impression
                window.onafterprint = function() { window.close(); };
            }
    </script>
    
</html>