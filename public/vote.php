<?php
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Models/Session.php';
require_once __DIR__ . '/../src/Models/UserStory.php';
require_once __DIR__ . '/../src/Models/Player.php';
require_once __DIR__ . '/../src/Models/Vote.php';

use App\Models\Session;
use App\Models\UserStory;
use App\Models\Player;
use App\Models\Vote;

session_start();
if (!isset($_SESSION['session_id'], $_SESSION['player_id'])) {
    header('Location: index.php');
    exit;
}

$pdo = getPDO();
$session = Session::findById($pdo, $_SESSION['session_id']);
$player = Player::findById($pdo, $_SESSION['player_id']);
$currentStory = UserStory::findCurrent($pdo, $session->id);
$allStories = UserStory::findBySession($pdo, $session->id);
$players = Player::findBySession($pdo, $session->id);
$stats = UserStory::getStats($pdo, $session->id);

$hasVoted = false;
$voteCount = 0;
if ($currentStory) {
    $hasVoted = Vote::hasPlayerVoted($pdo, $session->id, $currentStory->id, $player->id, 1);
    $voteCount = Vote::getVoteCount($pdo, $session->id, $currentStory->id, 1);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote - Planning Poker</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/chat.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="vote-bg">

<!-- Indicateur de chargement -->
<div class="loading-indicator" id="loading-indicator"></div>

<!-- Overlay de transition -->
<div class="transition-overlay" id="transition-overlay">
    <div class="transition-content">
        <h2><i class="fas fa-clipboard-list"></i> Nouvelle Story</h2>
        <p>Chargement en cours...</p>
    </div>
</div>

<!-- En-tête de session -->
    <!-- En-tête de session -->
    <div class="app-header">
        <div class="header-left">
            <div class="app-logo">
                <img src="assets/img/logo.svg" alt="Planning Poker Logo" width="32" height="32">
            </div>
            <div class="header-info">
                <span class="header-code">Code: <strong><?php echo htmlspecialchars($session->session_code); ?></strong></span>
                <?php if ($player->is_scrum_master): ?>
                    <span class="header-badge"><i class="fas fa-crown"></i> Scrum Master</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="header-actions">
            <button onclick="showBacklogModal()" class="header-btn" title="Backlog">
                <i class="fas fa-list"></i>
                <span>Backlog</span>
            </button>
            
            <?php if ($player->is_scrum_master): ?>
            <button onclick="showImportModal()" class="header-btn" title="Importer">
                <i class="fas fa-file-import"></i>
                <span>Importer</span>
            </button>
            
            <a href="export_json.php" class="header-btn" title="Exporter">
                <i class="fas fa-file-export"></i>
                <span>Exporter</span>
            </a>
            
            <?php endif; ?>
            
            <a href="results.php" class="header-btn" title="Résultats">
                <i class="fas fa-chart-bar"></i>
                <span>Résultats</span>
            </a>
            
            <a href="logout.php" class="header-btn header-btn-danger" title="Quitter">
                <i class="fas fa-sign-out-alt"></i>
                <span>Quitter</span>
            </a>
        </div>
    </div>
</div>

<div class="vote-container">

    <!-- Barre de progression -->
    <?php if ($stats['total'] > 0): ?>
    <div class="progress-container">
        <div class="progress-info">
            <span>Progression: <?php echo $stats['estimated']; ?>/<?php echo $stats['total']; ?> stories estimées</span>
        </div>
        <div class="progress-bar-container">
            <div class="progress-bar" style="width: <?php echo round(($stats['estimated'] / $stats['total']) * 100); ?>%"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Joueurs connectés -->
    <div class="players-section">
        <h3>Joueurs connectés (<span id="players-count"><?php echo count($players); ?></span>/<?php echo $session->max_players; ?>)</h3>
        <div class="players-grid" id="players-grid">
            <?php foreach ($players as $p): ?>
                <div class="player-card <?php echo $p->is_connected ? 'connected' : 'disconnected'; ?>" data-player-id="<?php echo $p->id; ?>">
                    <span class="player-icon"><?php echo $p->is_scrum_master ? '<i class="fas fa-crown"></i>' : '<i class="fas fa-user"></i>'; ?></span>
                    <span class="player-pseudo"><?php echo htmlspecialchars($p->pseudo); ?></span>
                    <?php if ($currentStory && Vote::hasPlayerVoted($pdo, $session->id, $currentStory->id, $p->id, 1)): ?>
                        <span class="vote-status"><i class="fas fa-check-circle"></i></span>
                    <?php else: ?>
                        <span class="vote-status pending"><i class="fas fa-clock"></i></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($currentStory): ?>
        <!-- User Story en cours -->
        <div class="story-section">
            <div class="story-card" data-story-id="<?php echo $currentStory->id; ?>">
                <div class="story-header">
                    <h2><?php echo htmlspecialchars($currentStory->title); ?></h2>
                    <span class="story-badge priority-<?php echo $currentStory->priority; ?>">
                        <?php echo ucfirst($currentStory->priority); ?>
                    </span>
                </div>
                <p class="story-description"><?php echo nl2br(htmlspecialchars($currentStory->description)); ?></p>
                <div class="story-meta">
                    <span>ID: <?php echo htmlspecialchars($currentStory->story_id); ?></span>
                    <span>Votes: <span id="vote-counter-meta"><?php echo $voteCount; ?></span>/<?php echo count($players); ?></span>
                </div>
            </div>

            <!-- Zone de vote - TOUJOURS VISIBLE -->
            <div class="voting-section">
                <h3>Choisissez votre carte</h3>
                <div class="cards-grid">
                    <?php 
                    $cards = [
                        ['value' => '0', 'display' => '0'],
                        ['value' => '1', 'display' => '1'],
                        ['value' => '2', 'display' => '2'],
                        ['value' => '3', 'display' => '3'],
                        ['value' => '5', 'display' => '5'],
                        ['value' => '8', 'display' => '8'],
                        ['value' => '13', 'display' => '13'],
                        ['value' => '20', 'display' => '20'],
                        ['value' => '40', 'display' => '40'],
                        ['value' => '100', 'display' => '100'],
                        ['value' => '?', 'display' => '?'],
                        ['value' => 'cafe', 'display' => '<i class="fas fa-coffee"></i>'],
                    ];
                    foreach ($cards as $card): 
                    ?>
                        <button class="card-vote <?php echo ($hasVoted || $session->status === 'coffee_break') ? 'disabled' : ''; ?>" 
                                data-value="<?php echo $card['value']; ?>"
                                onclick="voteCard('<?php echo $card['value']; ?>')"
                                <?php echo ($hasVoted || $session->status === 'coffee_break') ? 'disabled' : ''; ?>>
                            <span class="card-value"><?php echo $card['display']; ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($hasVoted): ?>
                    <div class="vote-confirmation">
                        <i class="fas fa-check-circle"></i> Vous avez voté ! En attente des autres joueurs...
                    </div>
                <?php endif; ?>

                <?php if ($session->status === 'coffee_break'): ?>
                    <div class="coffee-break-notice">
                        <i class="fas fa-coffee"></i> 
                        <strong>Pause café en cours</strong>
                        <p>Les votes sont bloqués. Le Scrum Master reprendra la session.</p>
                    </div>
                <?php endif; ?>

                <?php if ($player->is_scrum_master): ?>
                    <div class="sm-actions">
                        <?php if ($session->status === 'coffee_break'): ?>
                            <button onclick="resumeCoffeeBreak()" class="btn btn-success">
                                <i class="fas fa-play"></i> Reprendre le vote
                            </button>
                        <?php else: ?>
                            <button onclick="revealVotes()" class="btn btn-primary" data-vote-count
                                    <?php echo $voteCount === 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-eye"></i> Révéler les votes (<span id="vote-counter"><?php echo $voteCount; ?></span>)
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Aucune story -->
        <div class="no-story-section">
            <div class="no-story-card">
                <p class="no-story-icon"><i class="fas fa-clipboard-list"></i></p>
                <h3>Aucune user story à estimer</h3>
                <?php if ($player->is_scrum_master): ?>
                    <p>Importez un backlog pour commencer !</p>
                    <button onclick="showImportModal()" class="btn btn-primary"><i class="fas fa-file-import"></i> Importer un backlog</button>
                <?php else: ?>
                    <p>En attente que le Scrum Master importe un backlog...</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Import -->
<div id="import-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Importer un backlog JSON</h3>
            <button class="modal-close" onclick="closeImportModal()">&times;</button>
        </div>
        <form id="import-form" enctype="multipart/form-data">
            <div class="form-group">
                <label>Fichier JSON</label>
                <input type="file" name="backlog" accept="application/json,.json" required>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Importer</button>
                <button type="button" class="btn btn-secondary" onclick="closeImportModal()">Annuler</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Révélation -->
<div id="reveal-modal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Résultats du vote</h3>
            <button class="modal-close" onclick="closeRevealModal()">&times;</button>
        </div>
        <div id="reveal-content"></div>
    </div>
</div>

<script>
const isScrumMaster = <?php echo $player->is_scrum_master ? 'true' : 'false'; ?>;
const sessionId = <?php echo $session->id; ?>;
const playerId = <?php echo $player->id; ?>;

// Fonction pour ajuster le padding-top en fonction de la hauteur du header
function adjustContentPadding() {
    const header = document.querySelector('.app-header');
    const voteContainer = document.querySelector('.vote-container');
    
    if (header && voteContainer) {
        const headerHeight = header.offsetHeight;
        // Ajouter 24px de marge
        voteContainer.style.paddingTop = (headerHeight + 24) + 'px';
    }
}

// Ajuster au chargement
window.addEventListener('DOMContentLoaded', adjustContentPadding);

// Ajuster lors du redimensionnement de la fenêtre
let resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(adjustContentPadding, 100);
});

// Ajuster après le chargement complet (pour les polices personnalisées, etc.)
window.addEventListener('load', adjustContentPadding);

</script>
<script src="assets/js/vote.js?v=<?php echo time(); ?>"></script>
<script src="assets/js/chat.js?v=<?php echo time(); ?>"></script>

<!-- Chat flottant -->
<button class="chat-float-btn" id="chat-icon" onclick="toggleChat()" title="Chat de l'équipe">
    <i class="fas fa-comments"></i>
    <span class="chat-unread-badge" id="chat-unread-badge">0</span>
</button>

<!-- Panneau du chat -->
<div class="chat-panel" id="chat-panel">
    <div class="chat-header">
        <div class="chat-header-left">
            <div>
                <h3 class="chat-header-title">Chat de l'équipe</h3>
                <span class="chat-online-count" id="chat-online-count"><?php echo count($players); ?> en ligne</span>
            </div>
        </div>
        <button class="chat-close-btn" onclick="toggleChat()" title="Fermer">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="chat-messages" id="chat-messages">
        <div class="chat-empty">
            <i class="fas fa-comments"></i>
            <p>Aucun message pour le moment</p>
            <p style="font-size: 0.75rem; opacity: 0.7;">Soyez le premier à écrire !</p>
        </div>
    </div>
    
    <form class="chat-form" id="chat-form">
        <div class="chat-input-wrapper">
            <textarea 
                class="chat-input" 
                id="chat-input" 
                placeholder="Écrivez un message... "
                rows="1"
                maxlength="1000"
            ></textarea>
           
        </div>
    </form>
</div>

<script>
// Exposer l'ID du joueur pour le chat
const PLAYER_ID = <?php echo $player->id; ?>;
</script>

</body>
</html>