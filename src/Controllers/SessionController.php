<?php

namespace App\Controllers;

require_once __DIR__ . '/../Models/Session.php';
require_once __DIR__ . '/../Models/Player.php';

use App\Models\Session;
use App\Models\Player;
use PDO;

class SessionController
{
    public static function create(PDO $pdo): void
    {
        $name = trim($_POST['session_name'] ?? '');
        $max = (int)($_POST['max_players'] ?? 10);
        $rule = $_POST['vote_rule'] ?? 'strict';
        $pseudo = trim($_POST['pseudo'] ?? 'ScrumMaster');

        if (empty($name) || empty($pseudo)) {
            header('Location: create_form.php?error=missing_fields');
            exit;
        }

        $session = Session::create($pdo, $name, $max, $rule);
        $player = Player::create($pdo, $session->id, $pseudo, true);

        session_start();
        $_SESSION['session_id'] = $session->id;
        $_SESSION['player_id'] = $player->id;
        $_SESSION['is_scrum_master'] = true; // IMPORTANT: Définir explicitement

        header('Location: vote.php');
        exit;
    }

    public static function join(PDO $pdo): void
    {
        $code = strtoupper(trim($_POST['session_code'] ?? ''));
        $pseudo = trim($_POST['pseudo'] ?? '');

        if (empty($code) || empty($pseudo)) {
            header('Location: join_form.php?error=missing_fields');
            exit;
        }

        $session = Session::findByCode($pdo, $code);
        if (!$session) {
            header('Location: join_form.php?error=session_not_found');
            exit;
        }

        // Vérifier si la session n'est pas pleine
        $playerCount = $session->countPlayers($pdo);
        if ($playerCount >= $session->max_players) {
            header('Location: join_form.php?error=session_full');
            exit;
        }

        // Vérifier si le pseudo n'est pas déjà pris
        $players = Player::findBySession($pdo, $session->id);
        foreach ($players as $p) {
            if (strcasecmp($p->pseudo, $pseudo) === 0) {
                header('Location: join_form.php?error=pseudo_taken');
                exit;
            }
        }

        $player = Player::create($pdo, $session->id, $pseudo, false);

        session_start();
        $_SESSION['session_id'] = $session->id;
        $_SESSION['player_id'] = $player->id;
        $_SESSION['is_scrum_master'] = false; // IMPORTANT: Définir explicitement

        header('Location: vote.php');
        exit;
    }
}