<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'superviseur')) {
    header("Location: ../login_page.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_store_id = $_SESSION['store_id'];

$filter_type = $_GET['filter_type'] ?? 'custom';
$date_debut = $_GET['date_debut'] ?? date('Y-m-d');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');

// Calcul automatique des dates
switch ($filter_type) {
    case 'today': $date_debut = $date_fin = date('Y-m-d'); $period_name = "Aujourd'hui"; break;
    case 'week': $date_debut = date('Y-m-d', strtotime('monday this week')); $date_fin = date('Y-m-d', strtotime('sunday this week')); $period_name = "Cette Semaine"; break;
    case 'month': $date_debut = date('Y-m-01'); $date_fin = date('Y-m-t'); $period_name = "Ce Mois"; break;
    case 'year': $date_debut = date('Y-01-01'); $date_fin = date('Y-12-31'); $period_name = "Cette Année"; break;
    default: 
        $d_deb = date('d/m/Y', strtotime($date_debut));
        $d_fin = date('d/m/Y', strtotime($date_fin));
        $period_name = ($date_debut === $date_fin) ? "Le $d_deb" : "Du $d_deb au $d_fin";
}

$store_filter = ($user_role === 'superviseur') ? $user_store_id : ($_GET['store_id'] ?? '');

$conditions = ["1=1"];
$params = [];

if (!empty($store_filter)) { $conditions[] = "o.store_id = ?"; $params[] = $store_filter; }
if (!empty($date_debut)) { $conditions[] = "o.created_at >= ?"; $params[] = $date_debut . " 00:00:00"; }
if (!empty($date_fin)) { $conditions[] = "o.created_at <= ?"; $params[] = $date_fin . " 23:59:59"; }

$where_sql = "WHERE " . implode(" AND ", $conditions);

$query = "
    SELECT 
        s.name AS store_name,
        SUM(COALESCE(items_calc.calc_ventes, o.total_amount)) AS total_ventes,
        SUM(COALESCE(items_calc.calc_achats, 0)) AS total_achats,
        SUM(COALESCE(items_calc.calc_ventes, o.total_amount) - COALESCE(items_calc.calc_achats, 0)) AS benefice_total
    FROM orders o
    JOIN stores s ON o.store_id = s.id
    LEFT JOIN (
        SELECT 
            oi.order_id,
            SUM(oi.unit_price * oi.quantity) as calc_ventes,
            SUM(p.purchase_price * oi.quantity) as calc_achats
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        GROUP BY oi.order_id
    ) items_calc ON o.id = items_calc.order_id
    $where_sql
    GROUP BY s.id
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll();

$all_stores = ($user_role === 'superadmin') ? $pdo->query("SELECT id, name FROM stores ORDER BY name ASC")->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Rentabilité</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; color: #333; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); max-width: 1100px; margin: auto; }
        
        /* En-tête avec bouton retour stylé */
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-back-styled { text-decoration: none; color: #3498db; font-weight: bold; font-size: 16px; transition: 0.3s; }
        .btn-back-styled:hover { color: #2980b9; text-decoration: underline; }

        .filter-section { display: flex; flex-wrap: wrap; gap: 15px; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; align-items: flex-end; border: 1px solid #eef0f2; }
        
        /* Conteneur pour le scroll du tableau */
        .table-container { 
            width: 100%; 
            max-height: 450px; 
            overflow-y: auto; 
            border: 1px solid #eee;
            border-radius: 8px;
        }

        table { width: 100%; border-collapse: collapse; }
        
        /* En-tête fixe */
        th { 
            background: #2c3e50; 
            color: white; 
            padding: 12px; 
            text-align: left; 
            position: sticky; 
            top: 0; 
            z-index: 10;
        }

        td { padding: 12px; border-bottom: 1px solid #eee; }
        .profit-pos { color: #27ae60; font-weight: bold; }
        .profit-neg { color: #e74c3c; font-weight: bold; }
        .btn-filter { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
<div class="card">
    <div class="header-section">
        <h2 style="margin:0;">📊 Rentabilité : <?= $period_name ?></h2>
        <a href="dashboard.php" class="btn-back-styled">← Retour Dashboard</a>
    </div>
    
    <form method="GET" class="filter-section">
        <select name="filter_type" onchange="this.form.submit()" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
            <option value="custom" <?= $filter_type == 'custom' ? 'selected' : '' ?>>Personnalisé</option>
            <option value="today" <?= $filter_type == 'today' ? 'selected' : '' ?>>Aujourd'hui</option>
            <option value="month" <?= $filter_type == 'month' ? 'selected' : '' ?>>Ce Mois</option>
            <option value="year" <?= $filter_type == 'year' ? 'selected' : '' ?>>Cette Année</option>
        </select>
        
        <?php if ($filter_type == 'custom'): ?>
            <input type="date" name="date_debut" value="<?= $date_debut ?>" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
            <input type="date" name="date_fin" value="<?= $date_fin ?>" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
        <?php endif; ?>

        <?php if($user_role === 'superadmin'): ?>
        <select name="store_id" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
            <option value="">Toutes les boutiques</option>
            <?php foreach($all_stores as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $store_filter == $s['id'] ? 'selected' : '' ?>><?= $s['name'] ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        
        <button type="submit" class="btn-filter">Filtrer</button>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Boutique</th>
                    <th>Ventes (CA)</th>
                    <th>Coût d'Achat</th>
                    <th>Bénéfice Net 💰</th>
                    <th>Marge %</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($reports as $r): 
                    $marge = ($r['total_ventes'] > 0) ? ($r['benefice_total'] / $r['total_ventes']) * 100 : 0;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['store_name']) ?></strong></td>
                    <td><?= number_format($r['total_ventes'], 2, ',', ' ') ?> DA</td>
                    <td style="color: #e67e22;"><?= number_format($r['total_achats'], 2, ',', ' ') ?> DA</td>
                    <td class="<?= $r['benefice_total'] >= 0 ? 'profit-pos' : 'profit-neg' ?>">
                        <?= number_format($r['benefice_total'], 2, ',', ' ') ?> DA
                    </td>
                    <td><?= round($marge, 1) ?> %</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>