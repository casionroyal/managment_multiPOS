<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'superviseur'])) {
    header("Location: ../../login_page.php"); exit();
}

$role = $_SESSION['role'];
$current_store_id = $_SESSION['store_id'];

// REQUÊTE : On récupère les infos produits + le stock associé
$sql = "SELECT p.id, p.name, p.sku, p.purchase_price, p.selling_price, p.variant, c.name as cat_name, i.quantity, s.name as store_name, i.store_id 
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN inventory i ON p.id = i.product_id
        JOIN stores s ON i.store_id = s.id";

if ($role === 'superviseur') {
    $sql .= " WHERE i.store_id = ?";
}
$sql .= " ORDER BY p.name ASC";

$stmt = $pdo->prepare($sql);
if ($role === 'superviseur') { $stmt->execute([$current_store_id]); } 
else { $stmt->execute(); }
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion du Catalogue & Inventaire</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .table-container {
            max-height: 70vh;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-top: 10px;
        }
        .grid-table { width: 100%; border-collapse: collapse; background: white; }
        .grid-table th { 
            background: #34495e; 
            color: white; 
            padding: 12px; 
            text-align: left; 
            position: sticky; 
            top: 0; 
            z-index: 10;
        }
        .grid-table td { padding: 10px; border-bottom: 1px solid #eee; }
        .search-box {
            width: 100%;
            padding: 12px;
            margin-bottom: 10px;
            border: 2px solid #3498db;
            border-radius: 6px;
            font-size: 1em;
            box-sizing: border-box;
        }
        .input-edit { width: 90px; padding: 5px; border: 1px solid #ddd; border-radius: 4px; }
        .btn-update { background: #27ae60; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; color: white; }
        .bg-blue { background: #3498db; }
        .bg-orange { background: #e67e22; }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .action-btns { display: flex; gap: 8px; align-items: center; }
    </style>
</head>
<body>

<div class="header-actions">
    <h2>📦 Gestion du Catalogue & Inventaire</h2>
    <a href="dashboard.php" style="text-decoration:none; color:#3498db; font-weight:bold;">← Retour Dashboard</a>
</div>

<div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
    <button onclick="document.getElementById('addProdModal').style.display='block'" class="btn-update" style="background:#3498db; margin:0;">
        + Nouveau Produit
    </button>

    <?php if($role === 'superadmin'): ?>
        <a href="categories.php" style="background:#95a5a6; text-decoration:none; display:inline-block; padding:10px 15px; border-radius:4px; color:white; font-size:14px; font-weight: bold;">
            📂 Gérer les Catégories
        </a>
    <?php endif; ?>
</div>

<input type="text" id="searchInput" class="search-box" placeholder="Rechercher un produit, un SKU ou une catégorie...">

<div id="addProdModal" style="display:none; position:fixed; z-index:100; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); overflow-y: auto;">
    <div style="background:white; margin:2% auto; padding:20px; width:450px; border-radius:8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0; color: #2c3e50;">📦 Ajouter un nouveau produit</h3>
        
        <form action="add_product_logic.php" method="POST">
            <style>
                #addProdModal input, 
                #addProdModal select {
                    box-sizing: border-box; /* Force l'inclusion du padding et des bordures dans la largeur */
                    width: 100% !important; /* Force la largeur totale à ne pas dépasser 100% */
                    padding: 6px !important; /* Réduit l'espace intérieur pour des champs plus fins */
                    margin-bottom: 10px !important; 
                    border: 1px solid #ddd; 
                    border-radius: 4px;
                    font-family: inherit;
                    font-size: 14px;
                }
                #addProdModal label {
                    font-size: 14px;
                    font-weight: 600;
                    margin-bottom: 3px;
                    display: block;
                    color: #555;
                }
            </style>

            <label>Nom du produit :</label>
            <input type="text" name="name" required>

            <label>SKU (Code barre) :</label>
            <input type="text" name="sku" required>

            <label>Catégorie :</label>
            <div style="display:flex; gap:5px; margin-bottom:10px;">
                <select name="category_id" id="cat_select">
                    <?php
                    $cats = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
                    foreach($cats as $c) echo "<option value='{$c['id']}'>{$c['name']}</option>";
                    ?>
                </select>
                <button type="button" onclick="addNewCategory()" style="background:#2ecc71; color:white; border:none; padding:0 10px; border-radius:4px; cursor:pointer; font-weight:bold; height: 32px; align-self: center;">+</button>
            </div>

            <label>Stock de départ :</label>
            <input type="number" step="0.01" name="initial_qty" value="0" required style="background: #e8f8f5;">

            <?php if($role === 'superadmin'): ?>
                <label>Boutique d'affectation :</label>
                <select name="store_id" required>
                    <?php
                    $stores = $pdo->query("SELECT * FROM stores")->fetchAll();
                    foreach($stores as $s) echo "<option value='{$s['id']}'>{$s['name']}</option>";
                    ?>
                </select>
            <?php else: ?>
                <input type="hidden" name="store_id" value="<?= $current_store_id ?>">
            <?php endif; ?>

            <div style="background: #fff5f5; padding: 10px; border-radius: 5px; border: 1px solid #feb2b2; margin-bottom: 15px;">
                <label style="color: #c53030; font-weight: bold;">Prix d'Achat (Obligatoire pour la marge) :</label>
                <input type="number" step="0.01" name="purchase_price" min="0.01" required placeholder="0.00" style="border-color: #c53030 !important;">
                
                <label style="font-weight: bold;">Prix de Vente :</label>
                <input type="number" step="0.01" name="selling_price" min="0.01" required placeholder="0.00">
            </div>

            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" style="flex:2; background:#27ae60; color:white; border:none; padding:10px; border-radius:4px; cursor:pointer; font-weight:bold;">Créer le produit</button>
                <button type="button" onclick="document.getElementById('addProdModal').style.display='none'" style="flex:1; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer;">Annuler</button>
            </div>
        </form>
    </div>
</div>

<div class="table-container">
    <table class="grid-table" id="productTable">
        <thead>
            <tr>
                <th>Boutique</th>
                <th>Produit</th>
                <th>Catégorie</th>
                <th>Prix Achat</th>
                <th>Prix Vente</th>
                <th>Stock Actuel</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
                <td><span class="badge bg-blue"><?= htmlspecialchars($p['store_name']) ?></span></td>
                <td>
                    <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                    <small style="color:gray;">SKU: <?= $p['sku'] ?> | <?= htmlspecialchars($p['variant'] ?? '') ?></small>
                </td>
                <td><span class="badge bg-orange"><?= htmlspecialchars($p['cat_name']) ?></span></td>
                
                <td>
                    <?php if ($role === 'superadmin'): ?>
                        <input type="number" step="0.01" class="input-edit" id="buy-<?= $p['id'] ?>" value="<?= $p['purchase_price'] ?>">
                    <?php else: ?>
                        <b><?= number_format($p['purchase_price'], 2) ?> DA</b>
                    <?php endif; ?>
                </td>

                <td>
                    <?php if ($role === 'superadmin'): ?>
                        <input type="number" step="0.01" class="input-edit" id="sell-<?= $p['id'] ?>" value="<?= $p['selling_price'] ?>">
                    <?php else: ?>
                        <b style="color: #27ae60;"><?= number_format($p['selling_price'], 2) ?> DA</b>
                    <?php endif; ?>
                </td>

                <td>
                    <span style="font-weight:bold; <?= $p['quantity'] <= 5 ? 'color:red;' : '' ?>">
                        Qté: <?= (float)$p['quantity'] ?>
                    </span><br>
                    <input type="number" class="input-edit" id="add-qty-<?= $p['id'] ?>-<?= $p['store_id'] ?>" placeholder="+ Entrée">
                </td>

                <td>
                    <div class="action-btns">
                        <button class="btn-update" onclick="saveChanges(<?= $p['id'] ?>, <?= $p['store_id'] ?>)" title="Sauvegarder">✅</button>
                        
                        <?php if($role === 'superadmin'): ?>
                            <button class="btn-update" style="background:#e74c3c;" 
                                    onclick="deleteProduct(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>')" 
                                    title="Supprimer du catalogue">
                                🗑️
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
// Recherche en temps réel
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelector("#productTable tbody").rows;
    for (let i = 0; i < rows.length; i++) {
        let text = rows[i].textContent.toLowerCase();
        rows[i].style.display = text.includes(filter) ? "" : "none";
    }
});

// Enregistrer modifications (Prix + Stock)
function saveChanges(pId, sId) {
    const buyPrice = document.getElementById(`buy-${pId}`)?.value || null;
    const sellPrice = document.getElementById(`sell-${pId}`)?.value || null;
    const addedQty = document.getElementById(`add-qty-${pId}-${sId}`).value || 0;

    const payload = {
        product_id: pId,
        store_id: sId,
        purchase_price: buyPrice,
        selling_price: sellPrice,
        added_qty: addedQty
    };

    fetch('update_stock_logic.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            alert("Mise à jour réussie !");
            location.reload();
        } else {
            alert("Erreur: " + data.message);
        }
    });
}

// Suppression définitive (Catalogue)
function deleteProduct(id, name) {
    if (confirm("⚠️ ATTENTION : Voulez-vous supprimer '" + name + "' ?\n\nCela supprimera l'article du catalogue ET ses stocks dans toutes les boutiques !")) {
        window.location.href = "delete_product_logic.php?id=" + id;
    }
}

// Ajout rapide catégorie
function addNewCategory() {
    let newCat = prompt("Nom de la nouvelle catégorie :");
    if (newCat && newCat.trim() !== "") {
        fetch('add_category_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'name=' + encodeURIComponent(newCat)
        })
        .then(response => response.json())
        .then(data => {
            if(data.id) {
                let select = document.getElementById('cat_select');
                let opt = document.createElement('option');
                opt.value = data.id;
                opt.text = newCat;
                opt.selected = true;
                select.add(opt);
            }
        });
    }
}
</script>
</body>
</html>