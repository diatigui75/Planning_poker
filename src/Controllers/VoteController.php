<?php

namespace App\Controllers;

require_once __DIR__ . '/../Models/Vote.php';
require_once __DIR__ . '/../Models/UserStory.php';
require_once __DIR__ . '/../Models/Session.php';
require_once __DIR__ . '/../Models/Player.php';
require_once __DIR__ . '/../Services/VoteRulesService.php';

use App\Models\UserStory;
use App\Models\Vote;
use App\Models\Session;
use App\Models\Player;
use App\Services\VoteRulesService;
use PDO;

class VoteController
{
    public static function submitVote(PDO $pdo, int $sessionId, int $playerId, string $value): array
    {
        $story = UserStory::findCurrent($pdo, $sessionId);
        if (!$story) {
            return ['success' => false, 'error' => 'Aucune story en cours'];
        }

        // Mettre le statut de la story à "voting" si c'est le premier vote
        if ($story->status === 'pending') {
            $story->setStatus($pdo, 'voting');
        }

        Vote::addVote($pdo, $sessionId, $story->id, $playerId, $value, 1);
        
        return ['success' => true, 'message' => 'Vote enregistré'];
    }

    public static function startVoting(PDO $pdo, int $sessionId, int $storyId): array
    {
        $story = UserStory::findById($pdo, $storyId);
        if (!$story || $story->session_id !== $sessionId) {
            return ['success' => false, 'error' => 'Story introuvable'];
        }

        $session = Session::findById($pdo, $sessionId);
        $session->setCurrentStory($pdo, $storyId);
        $session->setStatus($pdo, 'voting');
        $story->setStatus($pdo, 'voting');

        return ['success' => true, 'message' => 'Vote démarré'];
    }

    public static function reveal(PDO $pdo, int $sessionId): array
    {
        $session = Session::findById($pdo, $sessionId);
        $story = UserStory::findCurrent($pdo, $sessionId);
        
        if (!$story) {
            return ['story' => null, 'votes' => [], 'result' => null];
        }

        $rows = Vote::getVotes($pdo, $sessionId, $story->id, 1);
        $votes = array_column($rows, 'vote_value');

        // Appliquer la règle de vote
        $result = VoteRulesService::computeResult($votes, $session->vote_rule);

        // IMPORTANT : On change SEULEMENT le statut à 'revealed'
        // On NE valide PAS l'estimation ici (c'est fait dans validateEstimation)
        $session->setStatus($pdo, 'revealed');
        
        // Si c'est une pause café, on le signale
        if (in_array('cafe', $votes)) {
            $result['coffee_break'] = true;
        }

        return [
            'story' => $story,
            'votes' => $rows,
            'result' => $result,
        ];
    }

    public static function revote(PDO $pdo, int $sessionId): array
    {
        $story = UserStory::findCurrent($pdo, $sessionId);
        if (!$story) {
            return ['success' => false, 'error' => 'Aucune story en cours'];
        }

        // Supprimer les votes du tour précédent
        Vote::clearVotes($pdo, $sessionId, $story->id, 1);
        
        // Réinitialiser le statut
        $story->setStatus($pdo, 'voting');
        $session = Session::findById($pdo, $sessionId);
        $session->setStatus($pdo, 'voting');

        return ['success' => true, 'message' => 'Nouveau tour de vote lancé'];
    }

    public static function validateEstimation(PDO $pdo, int $sessionId, int $storyId, int $estimation): array
    {
        $story = UserStory::findById($pdo, $storyId);
        if (!$story || $story->session_id !== $sessionId) {
            return ['success' => false, 'error' => 'Story introuvable'];
        }

        // Valider l'estimation de la story actuelle
        $story->setEstimation($pdo, $estimation);
        
        // Supprimer les votes de cette story pour libérer la mémoire
        Vote::clearVotes($pdo, $sessionId, $storyId, 1);
        
        // Chercher la prochaine story non estimée
        $stmt = $pdo->prepare("
            SELECT * FROM user_stories 
            WHERE session_id = :sid AND status = 'pending'
            ORDER BY order_index ASC 
            LIMIT 1
        ");
        $stmt->execute([':sid' => $sessionId]);
        $nextData = $stmt->fetch();
        
        $session = Session::findById($pdo, $sessionId);
        
        if ($nextData) {
            // Il y a une story suivante
            $nextStory = UserStory::fromArray($nextData);
            $nextStory->setStatus($pdo, 'voting'); // CHANGEMENT ICI: mettre directement en 'voting'
            $session->setCurrentStory($pdo, $nextStory->id);
            $session->setStatus($pdo, 'voting'); // CHANGEMENT ICI: mettre directement en 'voting'
            
            return ['success' => true, 'message' => 'Estimation validée', 'has_next' => true];
        } else {
            // Plus de stories à estimer
            $session->setCurrentStory($pdo, null);
            $session->setStatus($pdo, 'finished');
            
            return ['success' => true, 'message' => 'Toutes les stories sont estimées !', 'has_next' => false];
        }
    }

    public static function getSessionState(PDO $pdo, int $sessionId, int $playerId): array
    {
        $session = Session::findById($pdo, $sessionId);
        if (!$session) {
            return ['success' => false, 'error' => 'Session introuvable'];
        }
        
        $player = Player::findById($pdo, $playerId);
        $story = UserStory::findCurrent($pdo, $sessionId);
        $players = Player::findBySession($pdo, $sessionId);
        $stats = UserStory::getStats($pdo, $sessionId);

        $voteInfo = ['votes_count' => 0, 'has_voted' => false, 'votes' => []];
        
        if ($story) {
            $votes = Vote::getVotes($pdo, $sessionId, $story->id, 1);
            $hasVoted = Vote::hasPlayerVoted($pdo, $sessionId, $story->id, $playerId, 1);
            
            $voteInfo = [
                'story_id' => $story->id,
                'votes_count' => count($votes),
                'has_voted' => $hasVoted,
                'votes' => $votes,
            ];
        }

        return [
            'success' => true,
            'session' => [
                'id' => $session->id,
                'name' => $session->session_name,
                'code' => $session->session_code,
                'status' => $session->status,
                'vote_rule' => $session->vote_rule,
            ],
            'story' => $story ? [
                'id' => $story->id,
                'story_id' => $story->story_id,
                'title' => $story->title,
                'description' => $story->description,
                'priority' => $story->priority ?? 'moyenne',
                'status' => $story->status,
            ] : null,
            'players' => array_map(function($p) {
                return [
                    'id' => $p->id,
                    'pseudo' => $p->pseudo,
                    'is_scrum_master' => $p->is_scrum_master,
                    'is_connected' => $p->is_connected,
                ];
            }, $players),
            'vote_info' => $voteInfo,
            'stats' => $stats,
        ];
    }
}