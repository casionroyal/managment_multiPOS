<?php
session_start();
require '../config/db.php';

// Sécurité : Seul le Superadmin peut supprimer/renommer des catégories globalement
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    die("Accès réservé au Superadmin");
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestion Catégories</title>
    <link rel="stylesheet" href="../css/style.css"> </head>
<body>
    <div style="padding:20px;">
        <h2>📂 Gestion des Catégories</h2>
        <a href="products.php">← Retour aux Produits</a>

        <table style="width:100%; border-collapse: collapse; margin-top:20px;">
            <thead>
                <tr style="background:#34495e; color:white;">
                    <th style="padding:10px; text-align:left;">Nom de la catégorie</th>
                    <th style="padding:10px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($categories as $c): ?>
                <tr style="border-bottom:1px solid #ddd;">
                    <td style="padding:10px;">
                        <input type="text" id="cat-name-<?= $c['id'] ?>" value="<?= htmlspecialchars($c['name']) ?>" style="padding:5px; width:250px;">
                    </td>
                    <td style="padding:10px; text-align:right;">
                        <button onclick="renameCat(<?= $c['id'] ?>)" style="background:#2ecc71; color:white; border:none; padding:5px 10px; border-radius:3px; cursor:pointer;">Modifier</button>
                        
                        <button onclick="deleteCat(<?= $c['id'] ?>)" style="background:#e74c3c; color:white; border:none; padding:5px 10px; border-radius:3px; cursor:pointer; margin-left:5px;">Supprimer</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<script>
function renameCat(id) {
    const newName = document.getElementById('cat-name-' + id).value;
    if(!newName) return;

    fetch('category_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=rename&id=${id}&name=${encodeURIComponent(newName)}`
    }).then(() => alert('Nom mis à jour !'));
}

function deleteCat(id) {
    if(confirm("⚠️ Attention : Supprimer une catégorie peut poser problème si des produits y sont encore liés. Continuer ?")) {
        fetch('category_actions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=delete&id=${id}`
        }).then(r => r.json()).then(data => {
            if(data.status === 'success') location.reload();
            else alert(data.message);
        });
    }
}
</script>
</body>
</html>