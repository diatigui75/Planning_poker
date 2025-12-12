<?php
// API principale - Gestion complète
error_reporting(0);
ini_set('display_errors', 0);

// Toujours retourner du JSON
header('Content-Type: application/json; charset=utf-8');

// Fonction helper pour retourner du JSON
function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

// Gestionnaire d'erreurs
set_exception_handler(function($e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()]);
});

session_start();

if (!isset($_SESSION['session_id']) || !isset($_SESSION['player_id'])) {
    jsonResponse(['success' => false, 'error' => 'Non authentifié']);
}

// Charger les dépendances
try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/Models/Session.php';
    require_once __DIR__ . '/../src/Models/Player.php';
    require_once __DIR__ . '/../src/Models/UserStory.php';
    require_once __DIR__ . '/../src/Models/Vote.php';
    require_once __DIR__ . '/../src/Services/VoteRulesService.php';
    require_once __DIR__ . '/../src/Services/JsonManager.php';
    require_once __DIR__ . '/../src/Controllers/VoteController.php';
    require_once __DIR__ . '/../src/Controllers/BacklogController.php';
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Erreur chargement: ' . $e->getMessage()]);
}

use App\Controllers\VoteController;
use App\Controllers\BacklogController;

$pdo = getPDO();
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'get_session_state':
            $result = VoteController::getSessionState($pdo, $_SESSION['session_id'], $_SESSION['player_id']);
            jsonResponse($result);
            break;

        case 'get_backlog_list':
            try {
                $stories = \App\Models\UserStory::findBySession($pdo, $_SESSION['session_id']);
                $storiesData = array_map(function($story) {
                    return [
                        'id' => $story->id,
                        'story_id' => $story->story_id,
                        'title' => $story->title,
                        'priority' => $story->priority,
                        'estimation' => $story->estimation,
                        'status' => $story->status,
                        'order_index' => $story->order_index,
                    ];
                }, $stories);
                
                jsonResponse(['success' => true, 'stories' => $storiesData]);
            } catch (Exception $e) {
                jsonResponse(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'cast_vote':
            $voteValue = $_POST['vote_value'] ?? '';
            if (empty($voteValue)) {
                jsonResponse(['success' => false, 'error' => 'Vote invalide']);
            }
            $result = VoteController::submitVote($pdo, $_SESSION['session_id'], $_SESSION['player_id'], $voteValue);
            jsonResponse($result);
            break;

        case 'reveal_votes':
            if (!isset($_SESSION['is_scrum_master']) || !$_SESSION['is_scrum_master']) {
                jsonResponse(['success' => false, 'error' => 'Action réservée au Scrum Master']);
            }
            $data = VoteController::reveal($pdo, $_SESSION['session_id']);
            
            // Vérifier si c'est une pause café
            if (isset($data['result']['coffee_break']) && $data['result']['coffee_break']) {
                jsonResponse([
                    'success' => true,
                    'coffee_break' => true,
                    'story' => $data['story'] ? ['id' => $data['story']->id, 'title' => $data['story']->title] : null,
                    'votes' => $data['votes'],
                    'result' => $data['result'],
                ]);
            }
            
            jsonResponse([
                'success' => true,
                'story' => $data['story'] ? ['id' => $data['story']->id, 'title' => $data['story']->title] : null,
                'votes' => $data['votes'],
                'result' => $data['result'],
            ]);
            break;

        case 'revote':
            if (!isset($_SESSION['is_scrum_master']) || !$_SESSION['is_scrum_master']) {
                jsonResponse(['success' => false, 'error' => 'Action réservée au Scrum Master']);
            }
            $result = VoteController::revote($pdo, $_SESSION['session_id']);
            jsonResponse($result);
            break;

        case 'validate_estimation':
            if (!isset($_SESSION['is_scrum_master']) || !$_SESSION['is_scrum_master']) {
                jsonResponse(['success' => false, 'error' => 'Action réservée au Scrum Master']);
            }
            $storyId = (int)($_POST['story_id'] ?? 0);
            $estimation = (int)($_POST['estimation'] ?? 0);
            $result = VoteController::validateEstimation($pdo, $_SESSION['session_id'], $storyId, $estimation);
            jsonResponse($result);
            break;

        case 'validate_coffee_break':
            if (!isset($_SESSION['is_scrum_master']) || !$_SESSION['is_scrum_master']) {
                jsonResponse(['success' => false, 'error' => 'Action réservée au Scrum Master']);
            }
            $storyId = (int)($_POST['story_id'] ?? 0);
            
            // Sauvegarder automatiquement la session
            $json = \App\Services\JsonManager::saveSession($pdo, $_SESSION['session_id']);
            
            $result = VoteController::validateCoffeeBreak($pdo, $_SESSION['session_id'], $storyId);
            
            if ($result['success']) {
                $result['save_data'] = $json;
            }
            
            jsonResponse($result);
            break;

        case 'resume_coffee_break':
            if (!isset($_SESSION['is_scrum_master']) || !$_SESSION['is_scrum_master']) {
                jsonResponse(['success' => false, 'error' => 'Action réservée au Scrum Master']);
            }
            
            $result = VoteController::resumeFromCoffeeBreak($pdo, $_SESSION['session_id']);
            jsonResponse($result);
            break;

        case 'next_story':
            if (!isset($_SESSION['is_scrum_master']) || !$_SESSION['is_scrum_master']) {
                jsonResponse(['success' => false, 'error' => 'Action réservée au Scrum Master']);
            }
            
            try {
                // Trouver la story actuelle et la marquer comme ignorée
                $currentStory = \App\Models\UserStory::findCurrent($pdo, $_SESSION['session_id']);
                if ($currentStory) {
                    $currentStory->setStatus($pdo, 'estimated'); // Marquer comme traitée
                }
                
                // Chercher la prochaine story
                $stmt = $pdo->prepare("
                    SELECT * FROM user_stories 
                    WHERE session_id = :sid AND status = 'pending'
                    ORDER BY order_index ASC 
                    LIMIT 1
                ");
                $stmt->execute([':sid' => $_SESSION['session_id']]);
                $nextData = $stmt->fetch();
                
                $session = \App\Models\Session::findById($pdo, $_SESSION['session_id']);
                
                if ($nextData) {
                    $nextStory = \App\Models\UserStory::fromArray($nextData);
                    $session->setCurrentStory($pdo, $nextStory->id);
                    $session->setStatus($pdo, 'waiting');
                    jsonResponse(['success' => true, 'message' => 'Story suivante chargée']);
                } else {
                    $session->setCurrentStory($pdo, null);
                    $session->setStatus($pdo, 'finished');
                    jsonResponse(['success' => true, 'message' => 'Toutes les stories sont terminées']);
                }
            } catch (Exception $e) {
                jsonResponse(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'import_backlog':
            if (!isset($_SESSION['is_scrum_master']) || !$_SESSION['is_scrum_master']) {
                jsonResponse(['success' => false, 'error' => 'Action réservée au Scrum Master']);
            }
            if (!isset($_FILES['backlog'])) {
                jsonResponse(['success' => false, 'error' => 'Fichier manquant']);
            }
            $count = BacklogController::importJson($pdo, $_SESSION['session_id'], $_FILES['backlog']);
            jsonResponse(['success' => true, 'imported' => $count, 'message' => "$count user stories importées"]);
            break;

        case 'export_results':
            BacklogController::exportJson($pdo, $_SESSION['session_id']);
            break;

        case 'save_session':
            if (!isset($_SESSION['is_scrum_master']) || !$_SESSION['is_scrum_master']) {
                jsonResponse(['success' => false, 'error' => 'Action réservée au Scrum Master']);
            }
            BacklogController::saveSession($pdo, $_SESSION['session_id']);
            break;

        default:
            jsonResponse(['success' => false, 'error' => 'Action inconnue']);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()]);
}