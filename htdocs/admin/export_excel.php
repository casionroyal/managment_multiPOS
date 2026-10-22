<?php
session_start();
require '../config/db.php';

// Sécurité : Seul le patron (ou superviseur si tu l'autorises) peut exporter
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    exit("Accès refusé");
}

// 1. On récupère TOUS les filtres (Boutique, Vendeur + Dates)
$store_filter = $_GET['store_id'] ?? '';
$user_filter  = $_GET['user_id'] ?? '';
$date_debut   = $_GET['date_debut'] ?? '';
$date_fin     = $_GET['date_fin'] ?? '';

// 2. Construction de la requête SQL filtrée
$sql = "SELECT orders.id, orders.created_at, stores.name as store_name, users.username, orders.total_amount 
        FROM orders 
        JOIN stores ON orders.store_id = stores.id 
        JOIN users ON orders.user_id = users.id 
        WHERE 1=1";

$params = [];

if ($store_filter) { 
    $sql .= " AND orders.store_id = ?"; 
    $params[] = $store_filter; 
}
if ($user_filter) { 
    $sql .= " AND orders.user_id = ?"; 
    $params[] = $user_filter; 
}
if ($date_debut) { 
    $sql .= " AND DATE(orders.created_at) >= ?"; 
    $params[] = $date_debut; 
}
if ($date_fin) { 
    $sql .= " AND DATE(orders.created_at) <= ?"; 
    $params[] = $date_fin; 
}

$sql .= " ORDER BY orders.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Préparation du fichier CSV
$filename = "export_ventes_" . date('Y-m-d_H-i') . ".csv";

// Headers pour forcer le téléchargement
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=$filename");

$output = fopen("php://output", "w");

// UTF-8 BOM pour qu'Excel gère bien les accents et caractères spéciaux
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Titres des colonnes (DZD)
fputcsv($output, ['ID Vente', 'Date/Heure', 'Boutique', 'Vendeur', 'Montant Total (DZD)'], ";");

// 4. Écriture des données
foreach ($rows as $row) {
    // Optionnel : On peut reformater la date ici aussi pour le CSV
    $row['created_at'] = date('d/m/Y H:i', strtotime($row['created_at']));
    
    fputcsv($output, $row, ";");
}

fclose($output);
exit();