<?php
// 1. On démarre la session si elle n'existe pas déjà
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Forcé le timeout sur 40min 

ini_set('session.gc_maxlifetime', 2400);

// 2. On définit le chemin de la page de login (ajuste selon ton dossier)
$login_page = "https://" . $_SERVER['HTTP_HOST'] . "/login_page.php";

// 3. Si l'utilisateur n'est PAS connecté, on le renvoie au login
if (!isset($_SESSION['user_id'])) {
    header("Location: $login_page");
    exit();
}

require_once __DIR__ . '/config/db.php'; 

// 4. VERIFICATION DE L'EXPIRATION ET DU STATUT (FORCING LOGOUT)
$stmt = $pdo->prepare("SELECT expires_at, is_active FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$now = new DateTime();
$expiry = ($user && $user['expires_at']) ? new DateTime($user['expires_at']) : null;

// Si le compte est désactivé ou expiré : ON COUPE TOUT
if (!$user || $user['is_active'] == 0 || ($expiry && $now > $expiry)) {
    session_unset();
    session_destroy();
    header("Location: $login_page?error=expired");
    exit();
}

// 5. VERIFICATION DU TIMEOUT (INACTIVITÉ)
$timeout_duration = 2400; // 40 minutes (en secondes)

if (isset($_SESSION['last_action'])) {
    $elapsed_time = time() - $_SESSION['last_action'];
    
    if ($elapsed_time >= $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: $login_page?error=timeout");
        exit();
    }
}


// 6. Mise à jour du timestamp d'activité
$_SESSION['last_action'] = time();
    
    