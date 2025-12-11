<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning Poker - Accueil</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="home-bg">

<!-- Icône Règles du jeu en haut à droite (améliorée) -->
<button class="rules-icon-home" onclick="openRulesModal()" title="Règles du jeu">
    <i class="fas fa-question-circle"></i>
    <span>Règles</span>
</button>

<div class="home-container">
    <!-- Logo -->
    <div class="home-logo">
        <img src="assets/img/logo.svg" alt="Planning Poker Logo" width="90" height="90">
    </div>
    
    <h1 class="title">Planning Poker</h1>
    <p class="subtitle">Estimez vos User Stories en équipe</p>

    <div class="buttons">
        <a href="create_form.php" class="btn">Créer une session</a>
        <a href="join_form.php" class="btn btn-light">Rejoindre une session</a>
    </div>
    
</div>

<!-- Modale Règles du jeu -->
<div id="rulesModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Règles du Planning Poker</h3>
            <button class="modal-close" onclick="closeRulesModal()">&times;</button>
        </div>
        
        <div class="rules-content">
            <section class="rule-section">
                <h4>Objectif</h4>
                <p>Le Planning Poker est une technique d'estimation agile qui permet à l'équipe d'évaluer la complexité des User Stories de manière collaborative.</p>
            </section>

            <section class="rule-section">
                <h4>Déroulement</h4>
                <ol>
                    <li><strong>Présentation</strong> : Le Scrum Master importe le backlog</li>
                    <li><strong>Déroulement</strong> : Une user story s'affiche à la fois</li>
                    <li><strong>Discussion</strong> : L'équipe discute et pose des questions</li>
                    <li><strong>Vote</strong> : Chaque membre vote en secret avec une carte</li>
                    <li><strong>Révélation</strong> : Tous les votes sont révélés simultanément</li>
                    <li><strong>Consensus</strong> : L'équipe discute des écarts et revote si nécessaire</li>
                </ol>
            </section>

            <section class="rule-section">
                <h4>Les Cartes</h4>
                <div class="cards-preview">
                    <span class="card-preview">0</span>
                    <span class="card-preview">1</span>
                    <span class="card-preview">2</span>
                    <span class="card-preview">3</span>
                    <span class="card-preview">5</span>
                    <span class="card-preview">8</span>
                    <span class="card-preview">13</span>
                    <span class="card-preview">21</span>
                    <span class="card-preview">?</span>
                    <span class="card-preview"><i class="fas fa-coffee"></i></span>
                </div>
                <ul>
                    <li><strong>0-100</strong> : Points de complexité</li>
                    <li><strong>?</strong> : Je ne sais pas / Besoin de plus d'infos</li>
                    <li><strong><i class="fas fa-coffee"></i></strong> : Pause nécessaire</li>
                </ul>
            </section>

            <section class="rule-section">
                <h4>Règles de Vote</h4>
                <ul>
                    <li><strong>Unanimité</strong> : Tous les votes doivent être identiques</li>
                    <li><strong>Moyenne</strong> : La moyenne des votes est retenue</li>
                    <li><strong>Médiane</strong> : La valeur médiane est retenue</li>
                    <li><strong>Majorité absolue</strong> : Plus de 50% des votes identiques</li>
                    <li><strong>Majorité relative</strong> : Le vote le plus fréquent gagne</li>
                </ul>
            </section>

            <section class="rule-section">
                <h4>Conseils</h4>
                <ul>
                    <li>Votez selon votre propre jugement, pas celui des autres</li>
                    <li>Discutez des écarts importants entre les votes</li>
                    <li>N'hésitez pas à demander des précisions</li>
                    <li>Prenez des pauses si nécessaire (carte <i class="fas fa-coffee"></i>)</li>
                </ul>
            </section>
        </div>
    </div>
</div>

<script>
function openRulesModal() {
    document.getElementById('rulesModal').style.display = 'flex';
}

function closeRulesModal() {
    document.getElementById('rulesModal').style.display = 'none';
}

// Fermer la modale en cliquant en dehors
window.onclick = function(event) {
    const modal = document.getElementById('rulesModal');
    if (event.target === modal) {
        closeRulesModal();
    }
}

// Fermer avec la touche Echap
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeRulesModal();
    }
});
</script>

</body>
</html>