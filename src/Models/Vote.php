<?php

namespace App\Models;

use PDO;

class Vote
{
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