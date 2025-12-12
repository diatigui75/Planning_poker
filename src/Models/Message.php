<?php

namespace App\Models;

use PDO;

class Message
{
    public int $id;
    public int $session_id;
    public int $player_id;
    public string $player_name;
    public string $content;
    public string $created_at;

    /**
     * Créer la table messages si elle n'existe pas
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
     * Envoyer un message
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
     * Récupérer les messages d'une session
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
     * Compter le nombre de messages dans une session
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
     * Supprimer les anciens messages (nettoyage)
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
     * Supprimer tous les messages d'une session
     */
    public static function deleteBySession(PDO $pdo, int $sessionId): bool
    {
        $stmt = $pdo->prepare("DELETE FROM messages WHERE session_id = :session_id");
        return $stmt->execute([':session_id' => $sessionId]);
    }
}