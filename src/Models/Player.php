<?php

namespace App\Models;

use PDO;

class Player
{
    public int $id;
    public int $session_id;
    public string $pseudo;
    public bool $is_scrum_master;
    public bool $is_connected;

    public static function create(PDO $pdo, int $sessionId, string $pseudo, bool $scrumMaster = false): self
    {
        $stmt = $pdo->prepare("
            INSERT INTO players (session_id, pseudo, is_scrum_master, is_connected)
            VALUES (:session_id, :pseudo, :sm, 1)
        ");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':pseudo' => $pseudo,
            ':sm' => $scrumMaster ? 1 : 0,
        ]);

        return self::findById($pdo, (int)$pdo->lastInsertId());
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $stmt = $pdo->prepare("SELECT * FROM players WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch();
        return $data ? self::fromArray($data) : null;
    }

    public static function findBySession(PDO $pdo, int $sessionId): array
    {
        $stmt = $pdo->prepare("SELECT * FROM players WHERE session_id = :sid ORDER BY is_scrum_master DESC, joined_at ASC");
        $stmt->execute([':sid' => $sessionId]);
        $players = [];
        while ($row = $stmt->fetch()) {
            $players[] = self::fromArray($row);
        }
        return $players;
    }

    public function updateConnection(PDO $pdo, bool $connected): void
    {
        $this->is_connected = $connected;
        $stmt = $pdo->prepare("UPDATE players SET is_connected = :conn WHERE id = :id");
        $stmt->execute([
            ':conn' => $connected ? 1 : 0,
            ':id' => $this->id,
        ]);
    }

    public static function updateConnectionStatic(PDO $pdo, int $playerId, bool $connected): void
    {
        $stmt = $pdo->prepare("UPDATE players SET is_connected = :conn WHERE id = :id");
        $stmt->execute([
            ':conn' => $connected ? 1 : 0,
            ':id' => $playerId,
        ]);
    }

    public static function fromArray(array $data): self
    {
        $p = new self();
        $p->id = (int)$data['id'];
        $p->session_id = (int)$data['session_id'];
        $p->pseudo = $data['pseudo'];
        $p->is_scrum_master = (bool)$data['is_scrum_master'];
        $p->is_connected = (bool)$data['is_connected'];
        return $p;
    }
}