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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .results-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 80px 20px 30px 20px; /* padding-top initial, sera ajusté par JS */
            transition: padding-top 0.2s ease;
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
            background: var(--gray-100);
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
            border-collapse: separate;
            border-spacing: 0;
        }
        
        thead {
            background: transparent;
        }
        
        th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            color: #161616;
            font-size: 0.875rem;
            border-bottom: 2px solid #e0e0e0;
        }
        
        td {
            padding: 20px;
            border-bottom: 1px solid #f4f4f4;
            vertical-align: middle;
            font-size: 0.9375rem;
        }
        
        tbody tr {
            transition: background-color 0.2s ease;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Badge Priorité */
        .priority-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .priority-badge.haute {
            background: #ffe0e0;
            color: #c41e3a;
        }
        
        .priority-badge.moyenne {
            background: #e8e8e8;
            color: #525252;
        }
        
        .priority-badge.basse {
            background: #e8e8e8;
            color: #525252;
        }
        
        /* Badge Estimation */
        .estimation-value {
            display: inline-block;
            font-weight: 600;
            font-size: 1rem;
            color: #24a148;
        }
        
        /* Badge Statut */
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.8125rem;
        }
        
        .status-badge.estimated {
            background: #d4f4dd;
            color: #0f5132;
        }
        
        .status-badge.voting {
            background: #fff3cd;
            color: #997404;
        }
        
        .status-badge.pending {
            background: #fff3cd;
            color: #997404;
        }
        
        .actions-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 20px;
        }
        
        /* Responsive pour results */
        @media (max-width: 768px) {
            .results-header {
                padding: 20px;
            }
            
            .results-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .stat-value {
                font-size: 2rem;
            }
            
            .stat-label {
                font-size: 0.85rem;
            }
            
            .results-table {
                padding: 20px;
                overflow-x: auto;
            }
            
            table {
                font-size: 0.875rem;
            }
            
            th, td {
                padding: 10px 8px;
            }
            
            .actions-row {
                gap: 10px;
            }
            
            .actions-row .btn {
                font-size: 0.875rem;
                padding: 10px 16px;
            }
        }
    </style>
</head>
<body class="vote-bg">

<!-- En-tête de l'application -->
<div class="app-header">
    <div class="header-left">
        <div class="app-logo">
            <img src="assets/img/logo.svg" alt="Planning Poker Logo" width="32" height="32">
        </div>
        <div class="header-info">
            <span class="header-code">Code: <strong><?php echo htmlspecialchars($session->session_code); ?></strong></span>
            <?php if (isset($_SESSION['is_scrum_master']) && $_SESSION['is_scrum_master']): ?>
                <span class="header-badge"><i class="fas fa-crown"></i> Scrum Master</span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="header-actions">
        <a href="vote.php" class="header-btn" title="Retour au vote">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 2L2 8l6 6 6-6-6-6z"/>
            </svg>
            <span>Retour au vote</span>
        </a>
        
        <a href="export_json.php" class="header-btn" title="Exporter">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 14V6m0 0L5 9m3-3l3 3M2 9v3a1 1 0 001 1h10a1 1 0 001-1V9"/>
            </svg>
            <span>Exporter</span>
        </a>
        
        <a href="logout.php" class="header-btn header-btn-danger" title="Quitter">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M11 2h3v12h-3M7 11l3-3-3-3M10 8H2"/>
            </svg>
            <span>Quitter</span>
        </a>
    </div>
</div>

<div class="results-container">

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
                                <span class="priority-badge <?php echo strtolower($story->priority); ?>">
                                    <?php echo strtoupper($story->priority); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($story->estimation !== null): ?>
                                    <span class="estimation-value">
                                        <?php echo $story->estimation; ?> pts
                                    </span>
                                <?php else: ?>
                                    <span style="color: #8d8d8d;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($story->status === 'estimated'): ?>
                                    <span class="status-badge estimated"><i class="fas fa-check"></i> Estimée</span>
                                <?php elseif ($story->status === 'voting'): ?>
                                    <span class="status-badge voting"><i class="fas fa-clock"></i> En cours</span>
                                <?php else: ?>
                                    <span class="status-badge pending">En attente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="actions-row">
            <a href="export_json.php" class="btn btn-primary"><i class="fas fa-download"></i> Exporter en JSON</a>
            <a href="vote.php" class="btn btn-secondary">← Retour au vote</a>
        </div>
    </div>
</div>

<script>
// Fonction pour ajuster le padding-top en fonction de la hauteur du header
function adjustContentPadding() {
    const header = document.querySelector('.app-header');
    const resultsContainer = document.querySelector('.results-container');
    
    if (header && resultsContainer) {
        const headerHeight = header.offsetHeight;
        // Ajouter 24px de marge
        resultsContainer.style.paddingTop = (headerHeight + 24) + 'px';
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

// Ajuster après le chargement complet
window.addEventListener('load', adjustContentPadding);
</script>

</body>
</html>