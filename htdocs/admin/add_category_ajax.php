
<?php
session_start();
require '../config/db.php';
if($_POST['name']) {
    $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->execute([$_POST['name']]);
    echo json_encode(['id' => $pdo->lastInsertId()]);
}