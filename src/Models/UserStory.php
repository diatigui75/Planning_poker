<?php

namespace App\Models;

use PDO;

class UserStory
{
    public int $id;
    public int $session_id;
    public string $story_id;
    public string $title;
    public string $description;
    public string $priority;
    public ?int $estimation;
    public string $status;
    public int $order_index;

    public static function fromArray(array $d): self
    {
        $u = new self();
        $u->id = (int)$d['id'];
        $u->session_id = (int)$d['session_id'];
        $u->story_id = $d['story_id'];
        $u->title = $d['title'];
        $u->description = $d['description'] ?? '';
        $u->priority = $d['priority'];
        $u->estimation = $d['estimation'] !== null ? (int)$d['estimation'] : null;
        $u->status = $d['status'];
        $u->order_index = (int)$d['order_index'];
        return $u;
    }

    public static function importJson(PDO $pdo, int $sessionId, array $stories): void
    {
        // Supprimer les anciennes stories de la session
        $stmt = $pdo->prepare("DELETE FROM user_stories WHERE session_id = :sid");
        $stmt->execute([':sid' => $sessionId]);

        $order = 0;
        $stmt = $pdo->prepare("
            INSERT INTO user_stories (session_id, story_id, title, description, priority, order_index, status)
            VALUES (:sid, :story_id, :title, :desc, :prio, :ord, 'pending')
        ");

        foreach ($stories as $s) {
            $stmt->execute([
                ':sid' => $sessionId,
                ':story_id' => $s['id'],
                ':title' => $s['titre'],
                ':desc' => $s['description'] ?? '',
                ':prio' => $s['priorite'] ?? 'moyenne',
                ':ord' => $order++,
            ]);
        }
    }

    public static function findCurrent(PDO $pdo, int $sessionId): ?self
    {
        // D'abord chercher la story définie comme current_story_id dans la session
        $stmt = $pdo->prepare("
            SELECT us.* FROM user_stories us
            JOIN sessions s ON s.current_story_id = us.id
            WHERE s.id = :sid AND us.status IN ('pending', 'voting')
        ");
        $stmt->execute([':sid' => $sessionId]);
        $data = $stmt->fetch();
        
        if ($data) {
            return self::fromArray($data);
        }
        
        // Sinon, chercher la première story non estimée
        $stmt = $pdo->prepare("
            SELECT * FROM user_stories
            WHERE session_id = :sid AND status = 'pending'
            ORDER BY order_index ASC
            LIMIT 1
        ");
        $stmt->execute([':sid' => $sessionId]);
        $data = $stmt->fetch();
        
        if ($data) {
            $story = self::fromArray($data);
            // Mettre à jour la session pour pointer vers cette story
            $updateStmt = $pdo->prepare("UPDATE sessions SET current_story_id = :story_id WHERE id = :sid");
            $updateStmt->execute([':story_id' => $story->id, ':sid' => $sessionId]);
            return $story;
        }
        
        return null;
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $stmt = $pdo->prepare("SELECT * FROM user_stories WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch();
        return $data ? self::fromArray($data) : null;
    }

    public static function findBySession(PDO $pdo, int $sessionId): array
    {
        $stmt = $pdo->prepare("SELECT * FROM user_stories WHERE session_id = :sid ORDER BY order_index ASC");
        $stmt->execute([':sid' => $sessionId]);
        $stories = [];
        while ($row = $stmt->fetch()) {
            $stories[] = self::fromArray($row);
        }
        return $stories;
    }

    public function setEstimation(PDO $pdo, int $value): void
    {
        $this->estimation = $value;
        $this->status = 'estimated';
        $stmt = $pdo->prepare("
            UPDATE user_stories SET estimation = :e, status = 'estimated'
            WHERE id = :id
        ");
        $stmt->execute([
            ':e' => $value,
            ':id' => $this->id,
        ]);
    }

    public function setStatus(PDO $pdo, string $status): void
    {
        $this->status = $status;
        $stmt = $pdo->prepare("UPDATE user_stories SET status = :status WHERE id = :id");
        $stmt->execute([
            ':status' => $status,
            ':id' => $this->id,
        ]);
    }

    public static function getStats(PDO $pdo, int $sessionId): array
    {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'estimated' THEN 1 ELSE 0 END) as estimated,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'voting' THEN 1 ELSE 0 END) as voting
            FROM user_stories 
            WHERE session_id = :sid
        ");
        $stmt->execute([':sid' => $sessionId]);
        $result = $stmt->fetch();
        
        // S'assurer que tous les champs sont définis
        if (!$result) {
            return [
                'total' => 0,
                'estimated' => 0,
                'pending' => 0,
                'voting' => 0
            ];
        }
        
        return [
            'total' => (int)$result['total'],
            'estimated' => (int)$result['estimated'],
            'pending' => (int)$result['pending'],
            'voting' => (int)$result['voting']
        ];
    }
}