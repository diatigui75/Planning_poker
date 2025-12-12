<?php

namespace App\Controllers;  
use App\Models\Message;     
use App\Models\Player;
use PDO;

class ChatController
{
    /**
     * Envoyer un message dans le chat
     */
    public static function sendMessage(PDO $pdo, int $sessionId, int $playerId, string $content): array
    {
        // Vérifier que le joueur existe et appartient à la session
        $player = Player::findById($pdo, $playerId);
        
        if (!$player) {
            return ['success' => false, 'error' => 'Joueur non trouvé'];
        }

        // Nettoyer le contenu
        $content = trim($content);
        
        if (empty($content)) {
            return ['success' => false, 'error' => 'Message vide'];
        }

        if (strlen($content) > 1000) {
            return ['success' => false, 'error' => 'Message trop long (max 1000 caractères)'];
        }

        // Envoyer le message
        $success = Message::send($pdo, $sessionId, $playerId, $content);

        if ($success) {
            return [
                'success' => true,
                'message' => 'Message envoyé'
            ];
        } else {
            return ['success' => false, 'error' => 'Erreur lors de l\'envoi du message'];
        }
    }

    /**
     * Récupérer les messages du chat
     */
    public static function getMessages(PDO $pdo, int $sessionId, int $sinceId = 0): array
    {
        try {
            $messages = Message::getMessages($pdo, $sessionId, 50, $sinceId);
            
            return [
                'success' => true,
                'messages' => $messages,
                'count' => count($messages)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Récupérer le nombre de messages non lus
     */
    public static function getUnreadCount(PDO $pdo, int $sessionId, int $lastSeenMessageId): array
    {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM messages
                WHERE session_id = :session_id
                AND id > :last_seen_id
            ");
            
            $stmt->execute([
                ':session_id' => $sessionId,
                ':last_seen_id' => $lastSeenMessageId
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'unread_count' => (int)$result['count']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}