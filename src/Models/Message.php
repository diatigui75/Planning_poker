<?php

namespace App\Models;

use PDO;

/**
 * Modèle de gestion des messages du chat
 * 
 * Représente un message envoyé dans le chat d'une session Planning Poker.
 * Gère la persistance, la récupération et la suppression des messages.
 * 
 * @package App\Models
 * @author Melissa Aliouche
 */
class Message
{
    /** @var int Identifiant unique du message */
    public int $id;
    
    /** @var int Identifiant de la session à laquelle appartient le message */
    public int $session_id;
    
    /** @var int Identifiant du joueur auteur du message */
    public int $player_id;
    
    /** @var string Pseudo du joueur auteur */
    public string $player_name;
    
    /** @var string Contenu du message (max 1000 caractères) */
    public string $content;
    
    /** @var string Date et heure de création du message (format timestamp) */
    public string $created_at;

    /**
     * Crée la table messages dans la base de données si elle n'existe pas
     * 
     * Initialise la structure de la table avec les clés étrangères vers
     * sessions et players, et crée les index pour optimiser les requêtes.
     * Utilise le moteur InnoDB avec encodage UTF-8.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @return void
     */
    public static function createTable(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            player_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
            INDEX idx_session_id (session_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
    }

    /**
     * Envoie un message dans le chat d'une session
     * 
     * Valide le contenu (non vide et maximum 1000 caractères),
     * le nettoie et l'insère dans la base de données.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $playerId Identifiant du joueur émetteur
     * @param string $content Contenu du message à envoyer
     * @return bool True si l'envoi a réussi, false si validation échouée ou erreur
     */
    public static function send(PDO $pdo, int $sessionId, int $playerId, string $content): bool
    {
        // Nettoyer le contenu
        $content = trim($content);
        
        if (empty($content) || strlen($content) > 1000) {
            return false;
        }

        $stmt = $pdo->prepare("
            INSERT INTO messages (session_id, player_id, content)
            VALUES (:session_id, :player_id, :content)
        ");

        return $stmt->execute([
            ':session_id' => $sessionId,
            ':player_id' => $playerId,
            ':content' => $content
        ]);
    }

    /**
     * Récupère les messages d'une session avec informations des auteurs
     * 
     * Retourne une liste de messages avec le pseudo et le statut (Scrum Master)
     * de chaque auteur. Supporte le polling en ne récupérant que les messages
     * postérieurs à un ID donné.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $limit Nombre maximum de messages à récupérer (défaut: 50)
     * @param int $sinceId ID du dernier message reçu, 0 pour tout récupérer (défaut: 0)
     * @return array<array{id: int, session_id: int, player_id: int, content: string, created_at: string, player_name: string, is_scrum_master: bool}> Liste des messages avec détails des auteurs
     */
    public static function getMessages(PDO $pdo, int $sessionId, int $limit = 50, int $sinceId = 0): array
    {
        $query = "
            SELECT 
                m.id,
                m.session_id,
                m.player_id,
                m.content,
                m.created_at,
                p.pseudo as player_name,
                p.is_scrum_master
            FROM messages m
            JOIN players p ON m.player_id = p.id
            WHERE m.session_id = :session_id
        ";

        if ($sinceId > 0) {
            $query .= " AND m.id > :since_id";
        }

        $query .= " ORDER BY m.created_at ASC LIMIT :limit";

        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        if ($sinceId > 0) {
            $stmt->bindValue(':since_id', $sinceId, PDO::PARAM_INT);
        }

        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compte le nombre total de messages dans une session
     * 
     * Utile pour afficher des statistiques ou vérifier l'activité du chat.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @return int Nombre de messages dans la session
     */
    public static function countMessages(PDO $pdo, int $sessionId): int
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM messages WHERE session_id = :session_id
        ");
        $stmt->execute([':session_id' => $sessionId]);
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Supprime les messages anciens pour le nettoyage de la base
     * 
     * Fonction de maintenance pour supprimer automatiquement les messages
     * plus vieux qu'un nombre de jours spécifié.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $daysOld Nombre de jours d'ancienneté (défaut: 7)
     * @return int Nombre de messages supprimés
     */
    public static function deleteOldMessages(PDO $pdo, int $daysOld = 7): int
    {
        $stmt = $pdo->prepare("
            DELETE FROM messages 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute([':days' => $daysOld]);
        
        return $stmt->rowCount();
    }

    /**
     * Supprime tous les messages d'une session
     * 
     * Utilisé lors de la suppression d'une session ou pour réinitialiser
     * le chat. La suppression en cascade est gérée par la base de données.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @return bool True si la suppression a réussi, false sinon
     */
    public static function deleteBySession(PDO $pdo, int $sessionId): bool
    {
        $stmt = $pdo->prepare("DELETE FROM messages WHERE session_id = :session_id");
        return $stmt->execute([':session_id' => $sessionId]);
    }
}