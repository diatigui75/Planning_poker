<?php

namespace App\Models;

use PDO;

/**
 * Modèle de gestion des votes Planning Poker
 * 
 * Gère l'enregistrement, la récupération et la suppression des votes
 * des joueurs pour les user stories. Supporte les tours de vote multiples
 * et fournit des méthodes pour vérifier l'état des votes individuels
 * et collectifs.
 * 
 * @package App\Models
 * @author Melissa Aliouche
 */
class Vote
{
    /**
     * Enregistre ou met à jour le vote d'un joueur
     * 
     * Insère un nouveau vote ou met à jour un vote existant si le joueur
     * a déjà voté pour cette story dans ce tour. Utilise ON DUPLICATE KEY UPDATE
     * pour gérer l'unicité (session, story, joueur, tour).
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $storyId Identifiant de la user story
     * @param int $playerId Identifiant du joueur votant
     * @param string $value Valeur du vote (échelle Fibonacci, "cafe", "?", etc.)
     * @param int $round Numéro du tour de vote (défaut: 1)
     * @return void
     */
    public static function addVote(PDO $pdo, int $sessionId, int $storyId, int $playerId, string $value, int $round = 1): void
    {
        $stmt = $pdo->prepare("
            INSERT INTO votes (session_id, story_id, player_id, vote_value, vote_round)
            VALUES (:sid, :story, :pid, :val, :r)
            ON DUPLICATE KEY UPDATE vote_value = VALUES(vote_value), voted_at = NOW()
        ");
        $stmt->execute([
            ':sid' => $sessionId,
            ':story' => $storyId,
            ':pid' => $playerId,
            ':val' => $value,
            ':r' => $round,
        ]);
    }

    /**
     * Récupère tous les votes d'une story pour un tour donné
     * 
     * Retourne les votes avec les informations des joueurs (pseudo, ID).
     * Les résultats sont triés par rôle (Scrum Master en premier) puis
     * par pseudo alphabétique.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $storyId Identifiant de la user story
     * @param int $round Numéro du tour de vote (défaut: 1)
     * @return array<array{vote_value: string, pseudo: string, player_id: int, voted_at: string}> Liste des votes avec informations des joueurs
     */
    public static function getVotes(PDO $pdo, int $sessionId, int $storyId, int $round = 1): array
    {
        $stmt = $pdo->prepare("
            SELECT v.*, p.pseudo, p.id as player_id
            FROM votes v
            JOIN players p ON p.id = v.player_id
            WHERE v.session_id = :sid AND v.story_id = :story AND v.vote_round = :r
            ORDER BY p.is_scrum_master DESC, p.pseudo ASC
        ");
        $stmt->execute([
            ':sid' => $sessionId,
            ':story' => $storyId,
            ':r' => $round,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Supprime tous les votes d'une story pour un tour spécifique
     * 
     * Utilisé lors d'un revote ou après validation d'une estimation
     * pour nettoyer les votes du tour précédent.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $storyId Identifiant de la user story
     * @param int $round Numéro du tour de vote à supprimer
     * @return void
     */
    public static function clearVotes(PDO $pdo, int $sessionId, int $storyId, int $round): void
    {
        $stmt = $pdo->prepare("
            DELETE FROM votes 
            WHERE session_id = :sid AND story_id = :story AND vote_round = :r
        ");
        $stmt->execute([
            ':sid' => $sessionId,
            ':story' => $storyId,
            ':r' => $round,
        ]);
    }

    /**
     * Compte le nombre total de votes pour une story
     * 
     * Retourne le nombre de joueurs ayant voté pour la story
     * dans le tour spécifié. Utile pour vérifier si tous les
     * joueurs ont voté.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $storyId Identifiant de la user story
     * @param int $round Numéro du tour de vote (défaut: 1)
     * @return int Nombre de votes enregistrés
     */
    public static function getVoteCount(PDO $pdo, int $sessionId, int $storyId, int $round = 1): int
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cnt 
            FROM votes 
            WHERE session_id = :sid AND story_id = :story AND vote_round = :r
        ");
        $stmt->execute([
            ':sid' => $sessionId,
            ':story' => $storyId,
            ':r' => $round,
        ]);
        $result = $stmt->fetch();
        return (int)($result['cnt'] ?? 0);
    }

    /**
     * Vérifie si un joueur a déjà voté pour une story
     * 
     * Permet de déterminer si un joueur peut encore voter ou
     * s'il a déjà soumis son vote pour ce tour.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $storyId Identifiant de la user story
     * @param int $playerId Identifiant du joueur
     * @param int $round Numéro du tour de vote (défaut: 1)
     * @return bool True si le joueur a voté, false sinon
     */
    public static function hasPlayerVoted(PDO $pdo, int $sessionId, int $storyId, int $playerId, int $round = 1): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cnt 
            FROM votes 
            WHERE session_id = :sid AND story_id = :story AND player_id = :pid AND vote_round = :r
        ");
        $stmt->execute([
            ':sid' => $sessionId,
            ':story' => $storyId,
            ':pid' => $playerId,
            ':r' => $round,
        ]);
        $result = $stmt->fetch();
        return (int)($result['cnt'] ?? 0) > 0;
    }

    /**
     * Récupère le vote d'un joueur spécifique
     * 
     * Retourne la valeur du vote soumis par un joueur pour une story donnée.
     * Utile pour afficher le vote d'un joueur avant la révélation.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $storyId Identifiant de la user story
     * @param int $playerId Identifiant du joueur
     * @param int $round Numéro du tour de vote (défaut: 1)
     * @return string|null Valeur du vote, null si le joueur n'a pas voté
     */
    public static function getPlayerVote(PDO $pdo, int $sessionId, int $storyId, int $playerId, int $round = 1): ?string
    {
        $stmt = $pdo->prepare("
            SELECT vote_value 
            FROM votes 
            WHERE session_id = :sid AND story_id = :story AND player_id = :pid AND vote_round = :r
            LIMIT 1
        ");
        $stmt->execute([
            ':sid' => $sessionId,
            ':story' => $storyId,
            ':pid' => $playerId,
            ':r' => $round,
        ]);
        $result = $stmt->fetch();
        return $result ? $result['vote_value'] : null;
    }
}