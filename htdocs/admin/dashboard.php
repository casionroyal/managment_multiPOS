<?php
session_start();
require '../config/db.php';
require_once '../auth_check.php';

// --- RÉCUPÉRATION DES ALERTES DE STOCK ---

$alerts = [];
if (isset($_SESSION['role']) && $_SESSION['role'] === 'superviseur') {
    $store_id = $_SESSION['store_id'];
    // Vérifie bien que "i.min_threshold" est présent après le SELECT
    $queryAlerts = "
        SELECT p.name, i.quantity, i.min_threshold 
        FROM inventory i
        JOIN products p ON i.product_id = p.id
        WHERE i.store_id = ? AND i.quantity <= i.min_threshold
    ";
    $stmtAlerts = $pdo->prepare($queryAlerts);
    $stmtAlerts->execute([$store_id]);
    $alerts = $stmtAlerts->fetchAll(PDO::FETCH_ASSOC); // Utilise FETCH_ASSOC pour être sûr
}

// 1. SÉCURITÉ : Seuls Admin et Superviseur entrent
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'superviseur')) {
    header('Location: ../login_page.php'); 
    exit();
}

$user_role = $_SESSION['role'];
$user_store_id = $_SESSION['store_id']; 

// --- 2. FILTRAGE DES DROITS (SQL) ---
$where_store_id = ($user_role === 'superviseur') ? " AND store_id = $user_store_id " : "";
$where_orders   = ($user_role === 'superviseur') ? " AND orders.store_id = $user_store_id " : "";
$where_closings = ($user_role === 'superviseur') ? " AND dc.store_id = $user_store_id " : "";
$where_inventory = ($user_role === 'superviseur') ? " AND i.store_id = $user_store_id " : "";

// Liste des boutiques
if ($user_role === 'superadmin') {
    $all_stores = $pdo->query("SELECT * FROM stores ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    // L'admin voit TOUT le monde
    $all_users_full = $pdo->query("SELECT users.*, stores.name as shop_name FROM users LEFT JOIN stores ON users.store_id = stores.id WHERE users.is_active = 1 ORDER BY username ASC")->fetchAll();
} else {
    $stmtS = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
    $stmtS->execute([$user_store_id]);
    $all_stores = $stmtS->fetchAll(PDO::FETCH_ASSOC);
    // Le superviseur ne voit que SES vendeurs
    $stmtU = $pdo->prepare("SELECT users.*, stores.name as shop_name FROM users LEFT JOIN stores ON users.store_id = stores.id WHERE users.store_id = ? AND users.role = 'vendeur' AND users.is_active = 1 ORDER BY username ASC");
    $stmtU->execute([$user_store_id]);
    $all_users_full = $stmtU->fetchAll(); 
}

// Liste des vendeurs pour le filtre de recherche
if ($user_role === 'superadmin') {
    $vendeurs_filtre = $pdo->query("SELECT id, username FROM users WHERE role = 'vendeur' AND is_active = 1 ORDER BY username ASC")->fetchAll();
} else {
    $stmtV = $pdo->prepare("SELECT id, username FROM users WHERE role = 'vendeur' AND store_id = ? AND is_active = 1 ORDER BY username ASC");
    $stmtV->execute([$user_store_id]);
    $vendeurs_filtre = $stmtV->fetchAll();
}

// --- 3. CALCUL DES STATISTIQUES ---
$today = date('Y-m-d');
$sqlToday = "SELECT SUM(total_amount) FROM orders WHERE DATE(created_at) = ?" . $where_orders;
$resToday = $pdo->prepare($sqlToday);
$resToday->execute([$today]);
$recetteJour = $resToday->fetchColumn() ?: 0;

$month = date('Y-m');
$sqlMonth = "SELECT SUM(total_amount) FROM orders WHERE created_at LIKE ?" . $where_orders;
$resMonth = $pdo->prepare($sqlMonth);
$resMonth->execute([$month . '%']);
$recetteMois = $resMonth->fetchColumn() ?: 0;

// Alertes Stock
$sqlAlert = "SELECT p.name, s.name as store_name, i.quantity FROM inventory i JOIN products p ON i.product_id = p.id JOIN stores s ON i.store_id = s.id WHERE i.quantity < 5" . $where_inventory;
$alerts = $pdo->query($sqlAlert)->fetchAll();

// --- 4. FILTRAGE DES COMMANDES ---
$store_filter = ($user_role === 'superviseur') ? $user_store_id : ($_GET['store_id'] ?? '');
$user_filter  = $_GET['user_id'] ?? '';
$date_debut   = $_GET['date_debut'] ?? '';
$date_fin     = $_GET['date_fin'] ?? '';

$sql = "SELECT orders.*, stores.name as store_name, users.username FROM orders JOIN stores ON orders.store_id = stores.id JOIN users ON orders.user_id = users.id WHERE 1=1 " . $where_orders;
$params = [];
if ($user_role === 'superadmin' && $store_filter) { $sql .= " AND orders.store_id = ?"; $params[] = $store_filter; }
if ($user_filter) { $sql .= " AND orders.user_id = ?"; $params[] = $user_filter; }
if ($date_debut) { $sql .= " AND DATE(orders.created_at) >= ?"; $params[] = $date_debut; }
if ($date_fin) { $sql .= " AND DATE(orders.created_at) <= ?"; $params[] = $date_fin; }

$sql .= " ORDER BY orders.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// --- 5. CLÔTURES ---
$sqlClosings = "SELECT dc.*, s.name as store_name, u.username FROM daily_closings dc JOIN stores s ON dc.store_id = s.id JOIN users u ON dc.user_id = u.id WHERE 1=1 " . $where_closings . " ORDER BY dc.created_at DESC LIMIT 20";
$closings = $pdo->query($sqlClosings)->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Pilotage MultiPOS</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; margin: 0; }
        header { background: #2c3e50; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .stats-container { display: flex; gap: 20px; padding: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; flex: 1; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-top: 4px solid #27ae60; }
        .recette { font-size: 1.8em; font-weight: bold; color: #2c3e50; margin-top: 10px; }
        .actions-center { display: flex; flex-direction: column; gap: 10px; flex: 0.8; }
        .actions-center a { display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; font-weight: bold; font-size: 14px; border-radius: 8px; height: 100%; border: 1px solid #ddd; background: white; color: #2c3e50; transition: 0.3s; }
        .actions-center a:hover { border-color: #3498db; background: #f0f7ff; transform: scale(1.02); }
        .filter-bar { background: white; padding: 20px; margin: 0 20px; border-radius: 8px; display: flex; gap: 15px; align-items: flex-end; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .alert-box { background: #fff3cd; color: #856404; padding: 15px; margin: 20px; border-radius: 8px; border-left: 6px solid #ffc107; }
        .table-container { padding: 0 20px; }
        table { width: 100%; margin: 20px 0; background: white; border-collapse: collapse; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        th { background: #f8f9fa; padding: 15px; text-align: left; color: #7f8c8d; border-bottom: 2px solid #eee; }
        td { padding: 15px; border-bottom: 1px solid #eee; }
        .btn { padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 0.9em; cursor: pointer; display: inline-block; border: none; }
        .btn-blue { background: #3498db; color: white; }
        .btn-manage { background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 8px 16px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .scrollable-table { max-height: 400px; overflow-y: auto; border: 1px solid #eee; border-radius: 8px; }
        /* Modale */
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); justify-content: center; align-items: center; }
        .modal-content { background: #fff; width: 95%; max-width: 850px; border-radius: 15px; display: flex; flex-direction: column; }
        .modal-body { display: flex; max-height: 80vh; overflow-y: auto; }
        .modal-section { flex: 1; padding: 25px; }
        .modal-form { display: flex; flex-direction: column; gap: 10px; }
        .modal-form input, .modal-form select { padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
        .btn-save { background: #2ecc71; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn-icon { border: none; background: #f8f9fa; cursor: pointer; padding: 5px; border-radius: 4px; }
        .alert-box {
            background-color: #fff3cd;
            border-left: 5px solid #ffc107;
            color: #856404;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative; /* Pour que le bouton soit bien placé */
        }

        .alert-box ul li {
            margin-bottom: 3px;
        }

        .alert-box button:hover {
            color: #000 !important;
        }
    </style>
</head>
<body>

<header>
    <span>👑 <b><?= ($user_role === 'superadmin') ? 'ADMINISTRATION CENTRALE' : 'GESTION BOUTIQUE : ' . htmlspecialchars($all_stores[0]['name']) ?></b></span>
    
    <button class="btn-manage" onclick="ouvrirModale()">⚙️ Configuration <?= ($user_role === 'superviseur') ? 'Équipe' : 'Système' ?></button>

    <a href="../logout.php" style="color: #ecf0f1; text-decoration: none; font-weight: bold;">Déconnexion 🔒</a>
</header>

<div class="stats-container">
    <div class="stat-card">
        <small>RECETTE AUJOURD'HUI</small>
        <div class="recette"><?= number_format($recetteJour, 2, ',', ' ') ?> DZD</div>
    </div>
    <div class="actions-center">
        <a href="inventory.php">📦 Stock & Inventaire</a>
        <a href="products.php">🏷️ Catalogue Produits</a>
    </div>
    <div class="stat-card" style="border-top-color: #3498db;">
        <small>RECETTE DU MOIS</small>
        <div class="recette"><?= number_format($recetteMois, 2, ',', ' ') ?> DZD</div>
    </div>
</div>

<?php if ($_SESSION['role'] === 'superviseur' && !empty($alerts)): ?>
    <div class="alert-box" id="stockAlertBox" style="background-color: #fff3cd; border-left: 5px solid #ffc107; padding: 15px; margin: 20px; border-radius: 4px; position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <strong style="color: #856404;">⚠️ Attention : Stocks Faibles</strong>
                <ul style="margin: 10px 0 0 0; color: #856404;">
                    <?php foreach ($alerts as $a): ?>
                        <li>
                            <?= htmlspecialchars($a['name']) ?> : 
                            <b><?= (float)$a['quantity'] ?></b> 
                            <small>(Seuil: <?= isset($a['min_threshold']) ? $a['min_threshold'] : 'Non défini' ?>)</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button onclick="document.getElementById('stockAlertBox').style.display='none'" style="background:none; border:none; font-size: 20px; cursor:pointer;">&times;</button>
        </div>
    </div>
<?php endif; ?>

<form method="GET" class="filter-bar" id="filterForm">
    <div style="flex:1">
        <label>Boutique</label>
        <select name="store_id" style="width:100%; padding:8px;" <?= ($user_role !== 'superadmin') ? 'disabled' : '' ?>>
            <?php if($user_role === 'superadmin'): ?><option value="">Toutes les boutiques</option><?php endif; ?>
            <?php foreach($all_stores as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $store_filter == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if($user_role !== 'superadmin'): ?><input type="hidden" name="store_id" value="<?= $user_store_id ?>"><?php endif; ?>
    </div>

    <div style="flex:1">
        <label>Vendeur</label>
        <select name="user_id" style="width:100%; padding:8px;">
            <option value="">Tous les vendeurs</option>
            <?php foreach($vendeurs_filtre as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $user_filter == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="flex:1">
        <label>Du</label>
        <input type="date" name="date_debut" value="<?= $date_debut ?>" style="width:100%; padding:7px;">
    </div>

    <div style="flex:1">
        <label>Au</label>
        <input type="date" name="date_fin" value="<?= $date_fin ?>" style="width:100%; padding:7px;">
    </div>

<div style="display:flex; gap:10px; align-items: flex-end;">
    <button type="submit" class="btn btn-blue" style="height:40px;">Filtrer 🔍</button>
    
    <button type="button" onclick="exporterExcel()" class="btn" style="background-color: #27ae60; color: white; height:40px; border:none; padding:0 15px; border-radius:4px; font-weight:bold; cursor:pointer; display:flex; align-items:center; gap:5px;">
        Excel 📊
    </button>

    <a href="profit_report.php" class="btn" style="background-color: #f39c12; color: white; height:40px; border:none; padding:0 15px; border-radius:4px; font-weight:bold; cursor:pointer; display:flex; align-items:center; gap:5px; text-decoration:none; box-sizing: border-box;">
        Profit 💰
    </a>
</div>
</form>

<script>
function exporterExcel() {
    // On récupère le formulaire pour extraire les filtres choisis par l'utilisateur
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);

    // On redirige vers la page export_excel.php avec les filtres en paramètres
    window.location.href = 'export_excel.php?' + params.toString();
}
</script>

<div class="table-container">
    <h2>Historique des Ventes</h2>
    <div class="scrollable-table">
        <table>
            <thead>
                <tr><th>Date</th><th>Boutique</th><th>Vendeur</th><th>Total</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                    <td><b><?= htmlspecialchars($order['store_name']) ?></b></td>
                    <td><?= htmlspecialchars($order['username']) ?></td>
                    <td><?= number_format($order['total_amount'], 2, ',', ' ') ?> DZD</td>
                    <td><a href="order_details.php?id=<?= $order['id'] ?>" class="btn btn-blue">Détails</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 style="color: #c0392b; margin-top: 50px;">Clôtures de Caisse (Z)</h2>
    <div class="scrollable-table" style="border-top: 3px solid #c0392b;">
        <table>
            <thead>
                <tr><th>Date</th><th>Boutique</th><th>Vendeur</th><th>Théorique</th><th>Réel</th><th>Écart</th></tr>
            </thead>
            <tbody>
                <?php foreach ($closings as $c): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                    <td><?= htmlspecialchars($c['store_name']) ?></td>
                    <td><?= htmlspecialchars($c['username']) ?></td>
                    <td><?= number_format($c['total_expected'], 2, ',', ' ') ?></td>
                    <td><?= number_format($c['total_counted'], 2, ',', ' ') ?></td>
                    <td style="font-weight:bold; color:<?= $c['difference'] < 0 ? 'red' : 'green' ?>"><?= number_format($c['difference'], 2) ?> DZD</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="userModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header" style="padding:20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between;">
            <h3>⚙️ Configuration <?= ($user_role === 'superadmin') ? 'Système' : 'Équipe' ?></h3>
            <span onclick="fermerModale()" style="cursor:pointer; font-size:24px;">&times;</span>
        </div>
        <div class="modal-body">
            <div class="modal-section" style="<?= ($user_role !== 'superadmin') ? 'flex:2' : '' ?>">
                <h4>👥 Personnel</h4>
                <form action="process_user.php" method="POST" class="modal-form" id="userForm">
                    <input type="hidden" name="user_id" id="form_user_id">
                    <input type="text" name="username" id="form_username" placeholder="Nom d'utilisateur" required>
                    <input type="password" name="password" id="form_password" placeholder="Mot de passe">
                    
                    <?php if ($user_role === 'superadmin'): ?>
                        <select name="role" id="form_role">
                            <option value="vendeur">Vendeur</option>
                            <option value="superviseur">Superviseur</option>
                            <option value="superadmin">Patron</option>
                        </select>
                        <select name="store_id" id="form_store_id">
                            <option value="">Boutique...</option>
                            <?php foreach($all_stores as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" value="Rôle : Vendeur" disabled>
                        <input type="hidden" name="role" value="vendeur">
                        <input type="hidden" name="store_id" value="<?= $user_store_id ?>">
                    <?php endif; ?>

                    <button type="submit" name="save_user" id="submit_btn" class="btn-save">Enregistrer</button>
                    <button type="button" onclick="resetUserForm()" id="cancel_edit" style="display:none; margin-top:5px; background:#95a5a6; color:white; border:none; padding:10px; border-radius:6px;">Annuler</button>
                </form>

                <div style="max-height:250px; overflow-y:auto; margin-top:15px; border:1px solid #eee; border-radius:8px;">
                    <table style="font-size:12px; box-shadow:none; margin:0;">
                        <?php foreach($all_users_full as $u): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($u['username']) ?></b><br><small><?= strtoupper($u['role']) ?> - <?= htmlspecialchars($u['shop_name']) ?></small></td>
                            <td style="text-align:right;">
                                <button onclick="preparerModif('<?= $u['id'] ?>', '<?= addslashes($u['username']) ?>', '<?= $u['role'] ?>', '<?= $u['store_id'] ?>')" class="btn-icon">✏️</button>
                                <a href="process_user.php?delete_id=<?= $u['id'] ?>" onclick="return confirm('Désactiver cet utilisateur ?')" class="btn-icon">🗑️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <?php if ($user_role === 'superadmin'): ?>
            <div class="modal-section" style="border-left: 1px solid #eee;">
                <h4>🏪 Boutiques</h4>
                <form action="process_store.php" method="POST" class="modal-form">
                    <input type="text" name="store_name" placeholder="Nom de la nouvelle boutique" required>
                    <button type="submit" name="add_store" class="btn-save" style="background:#3498db;">Créer Boutique</button>
                </form>
                <div style="max-height:250px; overflow-y:auto; margin-top:15px; border:1px solid #eee; border-radius:8px;">
                    <table style="font-size:12px; box-shadow:none; margin:0;">
                        <?php foreach($all_stores as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td style="text-align:right;"><a href="process_store.php?delete_store=<?= $s['id'] ?>" onclick="return confirm('Supprimer cette boutique ?')" class="btn-icon">🗑️</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function ouvrirModale() { document.getElementById('userModal').style.display = 'flex'; }
    function fermerModale() { document.getElementById('userModal').style.display = 'none'; }
    
    function preparerModif(id, name, role, store_id) {
        document.getElementById('form_user_id').value = id;
        document.getElementById('form_username').value = name;
        
        // On ne change les select que s'ils existent (admin uniquement)
        const roleSelect = document.getElementById('form_role');
        if(roleSelect) roleSelect.value = role;
        
        const storeSelect = document.getElementById('form_store_id');
        if(storeSelect) storeSelect.value = store_id;

        document.getElementById('submit_btn').innerHTML = "Mettre à jour l'utilisateur";
        document.getElementById('submit_btn').style.background = "#e67e22";
        document.getElementById('cancel_edit').style.display = "block";
    }

    function resetUserForm() {
        document.getElementById('userForm').reset();
        document.getElementById('form_user_id').value = "";
        document.getElementById('submit_btn').innerHTML = "Enregistrer";
        document.getElementById('submit_btn').style.background = "#2ecc71";
        document.getElementById('cancel_edit').style.display = "none";
    }

    function closeAlert() {
        const box = document.getElementById('stockAlertBox');
        if (box) {
            box.style.display = 'none';
        }
    }

    // Optionnel : Envoie les dates vers profit_report.php quand on clique sur le bouton Profit
document.querySelector('a[href="profit_report.php"]').addEventListener('click', function(e) {
    e.preventDefault();
    const store = document.querySelector('select[name="store_id"]').value;
    const user = document.querySelector('select[name="user_id"]').value;
    const debut = document.querySelector('input[name="date_debut"]').value;
    const fin = document.querySelector('input[name="date_fin"]').value;
    
    window.location.href = `profit_report.php?store_id=${store}&user_id=${user}&date_debut=${debut}&date_fin=${fin}`;
});

</script>

</body>
</html>