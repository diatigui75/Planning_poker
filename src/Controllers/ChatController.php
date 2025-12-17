<?php

namespace App\Controllers;  
use App\Models\Message;     
use App\Models\Player;
use PDO;

/**
 * Contrôleur de gestion du système de chat
 * 
 * Gère l'envoi et la réception des messages du chat de session,
 * ainsi que le suivi des messages non lus.
 * 
 * @package App\Controllers
 * @author Melissa Aliouche
 */
class ChatController
{
    /**
     * Envoie un message dans le chat de la session
     * 
     * Vérifie l'existence du joueur, valide le contenu du message
     * (longueur, non vide) et l'enregistre dans la base de données.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $playerId Identifiant du joueur émetteur
     * @param string $content Contenu du message (max 1000 caractères)
     * @return array{success: bool, message?: string, error?: string} Résultat de l'envoi
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
     * Récupère les messages du chat d'une session
     * 
     * Retourne les 50 derniers messages de la session, avec possibilité
     * de ne récupérer que les messages postérieurs à un ID donné (polling).
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $sinceId ID du dernier message reçu (0 pour tout récupérer)
     * @return array{success: bool, messages?: array<array>, count?: int, error?: string} Liste des messages ou erreur
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
     * Compte le nombre de messages non lus
     * 
     * Calcule combien de messages ont été postés dans la session
     * depuis le dernier message vu par le joueur.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $lastSeenMessageId ID du dernier message vu par le joueur
     * @return array{success: bool, unread_count?: int, error?: string} Nombre de messages non lus ou erreur
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