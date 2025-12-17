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

/**
 * Contrôleur de gestion des votes Planning Poker
 * 
 * Gère l'ensemble du cycle de vie des votes : soumission, révélation,
 * validation des estimations, gestion des pauses café et de l'état
 * de la session. Coordonne les interactions entre les joueurs,
 * les user stories et les règles de vote.
 * 
 * @package App\Controllers
 * @author Melissa Aliouche
 */
class VoteController
{
    /**
     * Enregistre le vote d'un joueur pour la user story en cours
     * 
     * Vérifie l'existence de la story, bloque les votes pendant les pauses café,
     * et passe automatiquement la story en statut "voting" si c'est le premier vote.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $playerId Identifiant du joueur votant
     * @param string $value Valeur du vote (échelle Fibonacci, "cafe", "?", etc.)
     * @return array{success: bool, message?: string, error?: string} Résultat de l'enregistrement du vote
     */
    public static function submitVote(PDO $pdo, int $sessionId, int $playerId, string $value): array
    {
        $session = Session::findById($pdo, $sessionId);
        $story = UserStory::findCurrent($pdo, $sessionId);
        
        if (!$story) {
            return ['success' => false, 'error' => 'Aucune story en cours'];
        }

        // Bloquer les votes si on est en pause café
        if ($session->status === 'coffee_break') {
            return ['success' => false, 'error' => 'Vote bloqué : pause café en cours'];
        }

        // Mettre le statut de la story à "voting" si c'est le premier vote
        if ($story->status === 'pending') {
            $story->setStatus($pdo, 'voting');
        }

        Vote::addVote($pdo, $sessionId, $story->id, $playerId, $value, 1);
        
        return ['success' => true, 'message' => 'Vote enregistré'];
    }

    /**
     * Démarre le vote pour une user story spécifique
     * 
     * Définit la story comme story courante de la session et change
     * les statuts de la session et de la story en "voting".
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $storyId Identifiant de la user story à voter
     * @return array{success: bool, message?: string, error?: string} Résultat du démarrage du vote
     */
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

    /**
     * Révèle les votes de tous les joueurs et calcule le résultat
     * 
     * Affiche les votes soumis, applique la règle de vote configurée,
     * et détecte automatiquement les demandes de pause café unanimes.
     * Change le statut de la session en "revealed" pour afficher les résultats.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @return array{story: object|null, votes: array<array>, result: array{valid: bool, value: string|null, reason: string, coffee_break?: bool}|null, success?: bool, error?: string} Résultat avec votes et calcul
     */
    public static function reveal(PDO $pdo, int $sessionId): array
    {
        $session = Session::findById($pdo, $sessionId);
        $story = UserStory::findCurrent($pdo, $sessionId);
        
        // Bloquer si on est déjà en pause café
        if ($session->status === 'coffee_break') {
            return [
                'success' => false,
                'error' => 'Pause café en cours',
            ];
        }
        
        if (!$story) {
            return ['story' => null, 'votes' => [], 'result' => null];
        }

        $rows = Vote::getVotes($pdo, $sessionId, $story->id, 1);
        $votes = array_column($rows, 'vote_value');

        // Vérifier si c'est une pause café (TOUS les votes doivent être "cafe")
        $isCoffeeBreak = !empty($votes) && count(array_filter($votes, fn($v) => $v === 'cafe')) === count($votes);

        if ($isCoffeeBreak) {
            // Changer le statut de la session en 'revealed' d'abord pour afficher la modale
            // Le SM pourra ensuite valider pour passer en 'coffee_break'
            $session->setStatus($pdo, 'revealed');
            
            return [
                'story' => $story,
                'votes' => $rows,
                'result' => [
                    'valid' => true,
                    'coffee_break' => true,
                    'value' => 'cafe',
                    'reason' => 'Pause café unanime',
                ],
            ];
        }

        // Appliquer la règle de vote normale
        $result = VoteRulesService::computeResult($votes, $session->vote_rule);

        // Changer SEULEMENT le statut à 'revealed'
        $session->setStatus($pdo, 'revealed');

        return [
            'story' => $story,
            'votes' => $rows,
            'result' => $result,
        ];
    }

    /**
     * Lance un nouveau tour de vote pour la story en cours
     * 
     * Supprime tous les votes du tour précédent et réinitialise
     * les statuts de la session et de la story en "voting".
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @return array{success: bool, message?: string, error?: string} Résultat du lancement du nouveau tour
     */
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

    /**
     * Valide l'estimation d'une story et passe à la suivante
     * 
     * Enregistre l'estimation finale, nettoie les votes, et recherche
     * automatiquement la prochaine story à estimer. Si aucune story
     * n'est disponible, marque la session comme terminée.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $storyId Identifiant de la story à valider
     * @param int $estimation Valeur de l'estimation validée
     * @return array{success: bool, message: string, has_next: bool, error?: string} Résultat avec indication s'il reste des stories
     */
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
            $nextStory->setStatus($pdo, 'voting');
            $session->setCurrentStory($pdo, $nextStory->id);
            $session->setStatus($pdo, 'voting');
            
            return ['success' => true, 'message' => 'Estimation validée', 'has_next' => true];
        } else {
            // Plus de stories à estimer
            $session->setCurrentStory($pdo, null);
            $session->setStatus($pdo, 'finished');
            
            return ['success' => true, 'message' => 'Toutes les stories sont estimées !', 'has_next' => false];
        }
    }

    /**
     * Valide une pause café et bloque temporairement les votes
     * 
     * Active le mode pause café qui empêche les joueurs de voter
     * tout en conservant les votes existants pour affichage.
     * La story en cours reste inchangée.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $storyId Identifiant de la story en cours
     * @return array{success: bool, message: string, coffee_break_active: bool, error?: string} Résultat de l'activation de la pause
     */
    public static function validateCoffeeBreak(PDO $pdo, int $sessionId, int $storyId): array
    {
        $story = UserStory::findById($pdo, $storyId);
        if (!$story || $story->session_id !== $sessionId) {
            return ['success' => false, 'error' => 'Story introuvable'];
        }

        $session = Session::findById($pdo, $sessionId);

        // Garder le statut coffee_break et rester sur la même story
        // Ne PAS supprimer les votes pour pouvoir les afficher
        $session->setStatus($pdo, 'coffee_break');
        
        return [
            'success' => true, 
            'message' => 'Pause café validée ! Les votes sont bloqués.', 
            'coffee_break_active' => true
        ];
    }

    /**
     * Termine la pause café et reprend le vote sur la même story
     * 
     * Supprime les votes de la pause café, réinitialise les statuts
     * en "voting" pour permettre aux joueurs de voter à nouveau
     * sur la même user story.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @return array{success: bool, message: string, same_story: bool, error?: string} Résultat de la reprise
     */
    public static function resumeFromCoffeeBreak(PDO $pdo, int $sessionId): array
    {
        $session = Session::findById($pdo, $sessionId);
        $story = UserStory::findCurrent($pdo, $sessionId);
        
        if (!$story) {
            return ['success' => false, 'error' => 'Aucune story en cours'];
        }

        // Supprimer les votes de la pause café (pour permettre un nouveau vote)
        Vote::clearVotes($pdo, $sessionId, $story->id, 1);
        
        // Remettre la story en mode 'voting' (elle était restée en 'voting' pendant la pause)
        $story->setStatus($pdo, 'voting');
        
        // Remettre la session en mode 'voting'
        $session->setStatus($pdo, 'voting');
        
        return [
            'success' => true, 
            'message' => 'Pause café terminée ! Les joueurs peuvent maintenant voter pour estimer cette story.', 
            'same_story' => true
        ];
    }

    /**
     * Récupère l'état complet de la session pour un joueur
     * 
     * Compile toutes les informations nécessaires à l'affichage :
     * détails de la session, story en cours, liste des joueurs connectés,
     * informations de vote et statistiques. Calcule également le résultat
     * du vote si les votes ont été révélés.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session
     * @param int $playerId Identifiant du joueur demandeur
     * @return array{success: bool, session?: array, story?: array|null, players?: array<array>, vote_info?: array, vote_result?: array|null, stats?: array, error?: string} État complet de la session
     */
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
        $voteResult = null;
        
        if ($story) {
            $votes = Vote::getVotes($pdo, $sessionId, $story->id, 1);
            $hasVoted = Vote::hasPlayerVoted($pdo, $sessionId, $story->id, $playerId, 1);
            
            $voteInfo = [
                'story_id' => $story->id,
                'votes_count' => count($votes),
                'has_voted' => $hasVoted,
                'votes' => $votes,
            ];
            
            // Si le statut est 'revealed' ou 'coffee_break', calculer le résultat du vote
            if (in_array($session->status, ['revealed', 'coffee_break']) && count($votes) > 0) {
                $voteValues = array_column($votes, 'vote_value');
                
                // Vérifier si c'est une pause café
                $isCoffeeBreak = count(array_filter($voteValues, fn($v) => $v === 'cafe')) === count($voteValues);
                
                if ($isCoffeeBreak) {
                    $voteResult = [
                        'valid' => true,
                        'coffee_break' => true,
                        'value' => 'cafe',
                        'reason' => 'Pause café unanime',
                    ];
                } else {
                    $voteResult = VoteRulesService::computeResult($voteValues, $session->vote_rule);
                }
            }
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
            'vote_result' => $voteResult,
            'stats' => $stats,
        ];
    }
}