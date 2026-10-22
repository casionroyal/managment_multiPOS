<?php
session_start();
require '../config/db.php';
require_once '../auth_check.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'superviseur'])) {
    header("Location: ../login_page.php");
    exit();
}

$user_store_id = $_SESSION['store_id'];
$user_role = $_SESSION['role'];

// Requête SQL adaptée
$sql = "SELECT i.*, p.name, p.sku, p.variant, p.unit_type, s.name as shop_name 
        FROM inventory i 
        JOIN products p ON i.product_id = p.id 
        JOIN stores s ON i.store_id = s.id";

if ($user_role === 'superviseur') {
    $sql .= " WHERE i.store_id = " . intval($user_store_id);
}
$sql .= " ORDER BY s.name ASC, p.name ASC";
$inventory = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Stock & Inventaire</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); height: 90vh; display: flex; flex-direction: column; }
        
        h2 { color: #2c3e50; margin-top: 0; }
        
        /* Zone de recherche */
        .search-bar { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 16px; }

        /* CONTENEUR DE SCROLL */
        .table-wrapper { 
            flex: 1; 
            overflow-y: auto; 
            border: 1px solid #eee;
            border-radius: 8px;
        }

        table { width: 100%; border-collapse: collapse; }
        
        /* EN-TÊTE FIXE (Sticky) */
        th { 
            background: #34495e; 
            color: white; 
            padding: 15px; 
            text-align: left; 
            position: sticky; 
            top: 0; 
            z-index: 10;
        }

        td { padding: 12px; border-bottom: 1px solid #eee; }
        tr:hover { background-color: #f9f9f9; }

        /* Alertes Stock */
        .badge { padding: 6px 12px; border-radius: 20px; font-weight: bold; font-size: 0.85em; display: inline-block; }
        .danger { background: #ff7675; color: white; } 
        .success { background: #55efc4; color: #00b894; } 
        
        .qty-input { width: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; text-align: center; }
        .btn-add { background: #0984e3; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-add:hover { background: #0056b3; }

        .footer-link { margin-top: 15px; display: block; }

        .danger { 
            background: #ff7675; 
            color: white; 
            animation: pulse 2s infinite; 
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>

<div class="container">
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2 style="margin: 0;">📦 Gestion des Stocks <?= ($user_role === 'superviseur') ? "- Boutique " . htmlspecialchars($_SESSION['store_name'] ?? '') : "(Vue Globale)" ?></h2>
    <a href="dashboard.php" style="text-decoration:none; color:#3498db; font-weight:bold; display: flex; align-items: center; gap: 5px;">
        ← <span style="border-bottom: 1px solid #3498db;">Retour Dashboard</span>
    </a>
</div>
    
    <input type="text" id="searchInput" class="search-bar" placeholder="Rechercher un produit, SKU ou boutique...">

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <?php if($user_role === 'superadmin'): ?><th>Boutique</th><?php endif; ?>
                    <th>SKU</th>
                    <th>Produit</th>
                    <th>Variante</th>
                    <th>Stock Actuel</th>
                    <th>Seuil Min.</th>
                    <th>Action Arrivage</th>
                </tr>
            </thead>
<tbody id="inventoryBody">
    <?php foreach($inventory as $item): 
        // On calcule si le stock est bas
        $isLow = ($item['quantity'] <= $item['min_threshold']);
    ?>
    <tr>
        <?php if($user_role === 'superadmin'): ?>
            <td><b><?= htmlspecialchars($item['shop_name']) ?></b></td>
        <?php endif; ?>

        <td><code><?= htmlspecialchars($item['sku']) ?></code></td>

        <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>

        <td><small><?= htmlspecialchars($item['variant'] ?? '-') ?></small></td>

        <td>
            <span class="badge <?= $isLow ? 'danger' : 'success' ?>">
                <?= (float)$item['quantity'] ?> <?= htmlspecialchars($item['unit_type'] ?? '') ?>
            </span>
        </td>

        <td>
            <input type="number" 
                   id="min-threshold-<?= $item['id'] ?>" 
                   value="<?= (float)$item['min_threshold'] ?>" 
                   class="qty-input" 
                   style="width: 60px; background: #fffde7; border: 1px dashed #f1c40f; text-align:center;"
                   onchange="updateThreshold(<?= $item['id'] ?>)">
        </td>

        <td>
            <form action="process_inventory.php" method="POST" style="display:flex; gap:5px; margin:0;">
                <input type="hidden" name="inventory_id" value="<?= $item['id'] ?>">
                <input type="number" step="0.01" name="add_qty" class="qty-input" placeholder="+ Qté" required style="width:70px;">
                <button type="submit" name="update_stock" class="btn-add">Ajouter</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>
        </table>
    </div>


</div>

<script>
    // Filtrage dynamique
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('#inventoryBody tr');
        rows.forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
        });
    });
    function updateThreshold(inventoryId) {
    const newThreshold = document.getElementById(`min-threshold-${inventoryId}`).value;

    fetch('update_threshold_logic.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            inventory_id: inventoryId,
            threshold: newThreshold
        })
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            // Optionnel : un petit effet visuel pour confirmer
            console.log("Seuil mis à jour");
        } else {
            alert("Erreur lors de la mise à jour du seuil");
        }
    });
}
</script>

</body>
</html>