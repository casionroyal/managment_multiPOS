<?php
session_start();
require '../config/db.php';

// Sécurité : Vérifier le rôle
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'superviseur'])) {
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['inventory_id']) && isset($data['threshold'])) {
    $stmt = $pdo->prepare("UPDATE inventory SET min_threshold = ? WHERE id = ?");
    if ($stmt->execute([$data['threshold'], $data['inventory_id']])) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
}