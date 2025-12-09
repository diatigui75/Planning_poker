<?php
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Models/Session.php';
require_once __DIR__ . '/../src/Models/UserStory.php';

use App\Models\Session;
use App\Models\UserStory;

session_start();
if (!isset($_SESSION['session_id'])) {
    header('Location: index.php');
    exit;
}

$pdo = getPDO();
$session = Session::findById($pdo, $_SESSION['session_id']);
$stories = UserStory::findBySession($pdo, $session->id);
$stats = UserStory::getStats($pdo, $session->id);

// Calculer le total des points
$totalPoints = 0;
foreach ($stories as $story) {
    if ($story->estimation) {
        $totalPoints += $story->estimation;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultats - Planning Poker</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .results-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .results-header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .results-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #667eea 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 0.95rem;
            opacity: 0.9;
        }
        
        .results-table {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8f9fa;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .estimation-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .estimated {
            background: #d4edda;
            color: #155724;
        }
        
        .pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .actions-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 20px;
        }
    </style>
</head>
<body class="vote-bg">

<div class="results-container">
    <div class="results-header">
        <h1>📊 Résultats - <?php echo htmlspecialchars($session->session_name); ?></h1>
        <p style="color: #666; margin-top: 10px;">
            Code de session: <strong><?php echo htmlspecialchars($session->session_code); ?></strong>
        </p>
        
        <div class="results-stats">
            <div class="stat-card">
                <div class="stat-label">Total Stories</div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Estimées</div>
                <div class="stat-value"><?php echo $stats['estimated']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">En attente</div>
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Points</div>
                <div class="stat-value"><?php echo $totalPoints; ?></div>
            </div>
        </div>
    </div>

    <div class="results-table">
        <h2 style="margin-bottom: 20px;">Détail des User Stories</h2>
        
        <?php if (empty($stories)): ?>
            <p style="text-align: center; color: #999; padding: 40px;">
                Aucune user story dans le backlog.
            </p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Titre</th>
                        <th>Priorité</th>
                        <th>Estimation</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stories as $story): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($story->story_id); ?></strong></td>
                            <td><?php echo htmlspecialchars($story->title); ?></td>
                            <td>
                                <span class="story-badge priority-<?php echo $story->priority; ?>">
                                    <?php echo ucfirst($story->priority); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($story->estimation !== null): ?>
                                    <strong style="color: #28a745; font-size: 1.1rem;">
                                        <?php echo $story->estimation; ?> pts
                                    </strong>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($story->status === 'estimated'): ?>
                                    <span class="estimation-badge estimated">✓ Estimée</span>
                                <?php elseif ($story->status === 'voting'): ?>
                                    <span class="estimation-badge pending">⏳ En cours</span>
                                <?php else: ?>
                                    <span class="estimation-badge pending">⏸️ En attente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="actions-row">
            <a href="export_json.php" class="btn btn-primary">📥 Exporter en JSON</a>
            <a href="api.php?action=save_session" class="btn btn-secondary">💾 Sauvegarder la session</a>
            <a href="vote.php" class="btn btn-secondary">← Retour au vote</a>
        </div>
    </div>
</div>

</body>
</html>