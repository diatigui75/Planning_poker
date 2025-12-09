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
</head>

<body class="vote-bg">

<!-- Indicateur de chargement -->
<div class="loading-indicator" id="loading-indicator"></div>

<!-- Overlay de transition -->
<div class="transition-overlay" id="transition-overlay">
    <div class="transition-content">
        <h2>📋 Nouvelle Story</h2>
        <p>Chargement en cours...</p>
    </div>
</div>

<div class="vote-container">
    <!-- En-tête de session -->
    <div class="session-header">
        <div class="session-info">
            <h1>🃏 <?php echo htmlspecialchars($session->session_name); ?></h1>
            <p class="session-code">Code: <strong><?php echo htmlspecialchars($session->session_code); ?></strong></p>
            <p class="player-name">Connecté en tant que: <strong><?php echo htmlspecialchars($player->pseudo); ?></strong>
                <?php if ($player->is_scrum_master): ?>
                    <span class="badge-sm">Scrum Master</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="session-actions">
            <?php if ($player->is_scrum_master): ?>
                <button onclick="showImportModal()" class="btn-sm btn-secondary">📥 Importer backlog</button>
                <a href="export_json.php" class="btn-sm btn-secondary">📤 Exporter</a>
                <a href="api.php?action=save_session" class="btn-sm btn-secondary">💾 Sauvegarder</a>
            <?php endif; ?>
            <a href="results.php" class="btn-sm btn-secondary">📊 Résultats</a>
            <a href="logout.php" class="btn-sm btn-danger">🚪 Quitter</a>
        </div>
    </div>

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
                    <span class="player-icon"><?php echo $p->is_scrum_master ? '👑' : '👤'; ?></span>
                    <span class="player-pseudo"><?php echo htmlspecialchars($p->pseudo); ?></span>
                    <?php if ($currentStory && Vote::hasPlayerVoted($pdo, $session->id, $currentStory->id, $p->id, 1)): ?>
                        <span class="vote-status">✅</span>
                    <?php else: ?>
                        <span class="vote-status pending">⏳</span>
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
                        ['value' => 'cafe', 'display' => '☕'],
                    ];
                    foreach ($cards as $card): 
                    ?>
                        <button class="card-vote <?php echo $hasVoted ? 'disabled' : ''; ?>" 
                                data-value="<?php echo $card['value']; ?>"
                                onclick="voteCard('<?php echo $card['value']; ?>')"
                                <?php echo $hasVoted ? 'disabled' : ''; ?>>
                            <span class="card-value"><?php echo $card['display']; ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($hasVoted): ?>
                    <div class="vote-confirmation">
                        ✅ Vous avez voté ! En attente des autres joueurs...
                    </div>
                <?php endif; ?>

                <?php if ($player->is_scrum_master): ?>
                    <div class="sm-actions">
                        <button onclick="revealVotes()" class="btn btn-primary" data-vote-count
                                <?php echo $voteCount === 0 ? 'disabled' : ''; ?>>
                            🔍 Révéler les votes (<span id="vote-counter"><?php echo $voteCount; ?></span>)
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Aucune story -->
        <div class="no-story-section">
            <div class="no-story-card">
                <p class="no-story-icon">📋</p>
                <h3>Aucune user story à estimer</h3>
                <?php if ($player->is_scrum_master): ?>
                    <p>Importez un backlog pour commencer !</p>
                    <button onclick="showImportModal()" class="btn btn-primary">📥 Importer un backlog</button>
                <?php else: ?>
                    <p>En attente que le Scrum Master importe un backlog...</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Liste de toutes les stories -->
    <?php if (!empty($allStories)): ?>
    <div class="backlog-section">
        <h3>Backlog complet</h3>
        <div class="stories-list">
            <?php foreach ($allStories as $story): ?>
                <div class="story-item status-<?php echo $story->status; ?>">
                    <div class="story-item-header">
                        <span class="story-item-id"><?php echo htmlspecialchars($story->story_id); ?></span>
                        <span class="story-item-title"><?php echo htmlspecialchars($story->title); ?></span>
                    </div>
                    <div class="story-item-footer">
                        <span class="story-item-priority priority-<?php echo $story->priority; ?>">
                            <?php echo ucfirst($story->priority); ?>
                        </span>
                        <?php if ($story->estimation !== null): ?>
                            <span class="story-item-estimation">✓ <?php echo $story->estimation; ?> pts</span>
                        <?php else: ?>
                            <span class="story-item-status">
                                <?php 
                                    echo $story->status === 'voting' ? '⏳ En cours' : 
                                         ($story->status === 'pending' ? '⏸️ En attente' : '✅ Estimée');
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
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
</script>
<script src="assets/js/vote.js?v=<?php echo time(); ?>"></script>

</body>
</html>