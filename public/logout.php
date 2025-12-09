<?php
session_start();

// Déconnecter le joueur
if (isset($_SESSION['player_id'])) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/Models/Player.php';
    
    $pdo = getPDO();
    $player = \App\Models\Player::findById($pdo, $_SESSION['player_id']);
    if ($player) {
        $player->updateConnection($pdo, false);
    }
}

// Détruire la session
$sessionId = $_SESSION['session_id'] ?? null;
session_destroy();

// Rediriger vers l'accueil
header("Location: index.php" . ($sessionId ? "?left=1" : ""));
exit;