<?php

namespace App\Services;

use App\Models\UserStory;
use App\Models\Session;
use PDO;

/**
 * Service de gestion des imports/exports JSON pour Planning Poker
 * 
 * Gère l'importation de backlogs depuis des fichiers JSON,
 * l'exportation de backlogs avec estimations, et la sauvegarde
 * complète de sessions (incluant l'état et la progression).
 * Utilise un format JSON standardisé pour la portabilité des données.
 * 
 * @package App\Services
 * @author Melissa Aliouche
 */
class JsonManager
{
    /**
     * Importe un backlog de user stories depuis un JSON
     * 
     * Parse le JSON, valide sa structure (présence de la clé "stories"),
     * et importe toutes les stories dans la session spécifiée.
     * Les stories existantes de la session sont remplacées.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session cible
     * @param string $json Chaîne JSON contenant le backlog à importer
     * @return int Nombre de stories importées avec succès
     * @throws \RuntimeException Si le JSON est invalide ou si la clé "stories" est manquante
     */
    public static function importBacklog(PDO $pdo, int $sessionId, string $json): int
    {
        $data = json_decode($json, true);
        if (!isset($data['stories']) || !is_array($data['stories'])) {
            throw new \RuntimeException('JSON invalide: clé "stories" manquante');
        }
        UserStory::importJson($pdo, $sessionId, $data['stories']);
        return count($data['stories']);
    }

    /**
     * Exporte le backlog d'une session au format JSON
     * 
     * Génère un fichier JSON contenant toutes les informations de la session
     * et ses user stories avec leurs estimations. Le format est compatible
     * avec la fonction d'import pour permettre la réutilisation des backlogs.
     * Utilise JSON_PRETTY_PRINT pour une meilleure lisibilité.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session à exporter
     * @return string JSON formaté contenant le backlog complet avec métadonnées
     */
    public static function exportBacklog(PDO $pdo, int $sessionId): string
    {
        $session = Session::findById($pdo, $sessionId);
        $stories = UserStory::findBySession($pdo, $sessionId);
        
        $export = [
            'session_id' => $sessionId,
            'session_name' => $session->session_name,
            'session_code' => $session->session_code,
            'vote_rule' => $session->vote_rule,
            'exported_at' => date('Y-m-d H:i:s'),
            'stories' => [],
        ];
        
        foreach ($stories as $s) {
            $export['stories'][] = [
                'id' => $s->story_id,
                'titre' => $s->title,
                'description' => $s->description,
                'priorite' => $s->priority,
                'estimation' => $s->estimation,
                'status' => $s->status,
            ];
        }
        
        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sauvegarde complète d'une session avec son état
     * 
     * Crée une sauvegarde exhaustive incluant tous les paramètres de la session,
     * son statut actuel, la story en cours, et toutes les user stories avec
     * leurs estimations et positions. La sauvegarde est également enregistrée
     * dans la table session_saves pour historique. Utilise JSON_PRETTY_PRINT
     * et JSON_UNESCAPED_UNICODE pour un format lisible.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session à sauvegarder
     * @return string JSON formaté contenant la sauvegarde complète de la session
     */
    public static function saveSession(PDO $pdo, int $sessionId): string
    {
        $session = Session::findById($pdo, $sessionId);
        $stories = UserStory::findBySession($pdo, $sessionId);
        
        $save = [
            'session' => [
                'id' => $session->id,
                'name' => $session->session_name,
                'code' => $session->session_code,
                'vote_rule' => $session->vote_rule,
                'status' => $session->status,
                'current_story_id' => $session->current_story_id,
            ],
            'stories' => [],
            'saved_at' => date('Y-m-d H:i:s'),
        ];
        
        foreach ($stories as $s) {
            $save['stories'][] = [
                'id' => $s->story_id,
                'titre' => $s->title,
                'description' => $s->description,
                'priorite' => $s->priority,
                'estimation' => $s->estimation,
                'status' => $s->status,
                'order_index' => $s->order_index,
            ];
        }
        
        $json = json_encode($save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // Sauvegarder dans la table session_saves
        $stmt = $pdo->prepare("
            INSERT INTO session_saves (session_id, save_data) 
            VALUES (:sid, :data)
        ");
        $stmt->execute([
            ':sid' => $sessionId,
            ':data' => $json,
        ]);
        
        return $json;
    }
}