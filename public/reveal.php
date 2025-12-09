<?php
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Controllers/VoteController.php';
require_once __DIR__ . '/../src/Models/Session.php';
require_once __DIR__ . '/../src/Models/Player.php';
require_once __DIR__ . '/../src/Models/UserStory.php';
require_once __DIR__ . '/../src/Models/Vote.php';
require_once __DIR__ . '/../src/Services/VoteRulesService.php';


use App\Controllers\VoteController;
use App\Models\Session;
use App\Models\Player;

session_start();
if (!isset($_SESSION['session_id'], $_SESSION['player_id'])) {
    header('Location: index.php');
    exit;
}

$pdo = getPDO();
$session = Session::findById($pdo, $_SESSION['session_id']);
$player = Player::findById($pdo, $_SESSION['player_id']);

if (!$player->is_scrum_master) {
    header('Location: vote.php');
    exit;
}

$data = VoteController::reveal($pdo, $session->id);
$story = $data['story'];
$votes = $data['votes'];
$result = $data['result'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Révélation - Planning Poker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<h1>Votes révélés</h1>

<?php if ($story): ?>
    <h2>Story : <?php echo htmlspecialchars($story->title); ?></h2>

    <table border="1" cellpadding="5">
        <tr><th>Joueur</th><th>Vote</th></tr>
        <?php foreach ($votes as $v): ?>
            <tr>
                <td><?php echo htmlspecialchars($v['pseudo']); ?></td>
                <td><?php echo htmlspecialchars($v['vote_value']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <?php if ($result): ?>
        <p>Résultat règle : <?php echo htmlspecialchars($result['reason']); ?></p>
        <?php if ($result['valid']): ?>
            <p>Estimation retenue : <?php echo htmlspecialchars((string)$result['value']); ?></p>
        <?php else: ?>
            <p>Règle non satisfaite, revote nécessaire.</p>
        <?php endif; ?>
    <?php endif; ?>
<?php else: ?>
    <p>Aucune story.</p>
<?php endif; ?>

<p><a href="vote.php">Retour au vote</a></p>
</body>
</html>
