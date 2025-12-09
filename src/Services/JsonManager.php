<?php

namespace App\Services;

use App\Models\UserStory;
use App\Models\Session;
use PDO;

class JsonManager
{
    public static function importBacklog(PDO $pdo, int $sessionId, string $json): int
    {
        $data = json_decode($json, true);
        if (!isset($data['stories']) || !is_array($data['stories'])) {
            throw new \RuntimeException('JSON invalide: clé "stories" manquante');
        }
        UserStory::importJson($pdo, $sessionId, $data['stories']);
        return count($data['stories']);
    }

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