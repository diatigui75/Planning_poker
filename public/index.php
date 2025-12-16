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
                <h4>Mise en place</h4>
                <ul>
                    <li>Choisissez un animateur (Scrum Master) : gérer le temps et animer les discussions.</li>
                    <li>Le Scrum Master crée la session et partage le code avec l’équipe pour qu’ils puissent rejoindre.</li>
                    <li>Les autres membres jouent le rôle de l’équipe de développement.</li>
                    <li>Le chat intégré permet de discuter en temps réel pendant le vote.</li>
                </ul>
            </section>

            <section class="rule-section">
                <h4>Déroulement</h4>
                <ol>
                    <li>Une User Story s'affiche.</li>
                    <li>Chaque membre choisit une carte représentant son estimation de complexité.</li>
                    <li>Petit chiffre : tâche simple, rapide, bien comprise.</li>
                    <li>Grand chiffre : tâche complexe, longue ou incertaine.</li>
                    <li>Carte <i class="fas fa-coffee"></i> : besoin d'une pause.</li>
                    <li>Carte <strong>?</strong> : je ne me sens pas compétent pour estimer.</li>
                    <li>Le Scrum Master révèle les cartes une fois que tout le monde a voté.</li>
                    <li>Si des écarts importants apparaissent :
                        <ul>
                            <li>Les personnes ayant donné les valeurs extrêmes expliquent leur point de vue.</li>
                            <li>L’équipe discute brièvement.</li>
                            <li>Un nouveau vote est fait jusqu’à consensus.</li>
                        </ul>
                    </li>
                </ol>
            </section>

            <section class="rule-section">
                <h4>Les Cartes disponibles</h4>
                <div class="cards-preview">
                    <span class="card-preview">0</span>
                    <span class="card-preview">1</span>
                    <span class="card-preview">2</span>
                    <span class="card-preview">3</span>
                    <span class="card-preview">5</span>
                    <span class="card-preview">8</span>
                    <span class="card-preview">13</span>
                    <span class="card-preview">20</span>
                    <span class="card-preview">40</span>
                    <span class="card-preview">100</span>
                    <span class="card-preview">?</span>
                    <span class="card-preview"><i class="fas fa-coffee"></i></span>
                </div>
                <ul>
                    <li><strong>0 - 100</strong> : Points de complexité</li>
                    <li><strong>?</strong> : Je ne sais pas / Besoin de plus d'infos</li>
                    <li><strong><i class="fas fa-coffee"></i></strong> : Pause nécessaire</li>
                </ul>
            </section>
            <section class="rule-section">
                <h4>Import du Backlog</h4>
                <p>Le Scrum Master peut importer un fichier JSON contenant les User Stories à estimer.</p>
                <p><strong>Exemple de fichier JSON :</strong></p>
                <pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 0.9em; color: #000;">{
    "stories": [
        {
            "id": "US001",
            "titre": "Créer la page d'accueil",
            "description": "Afficher un menu principal avec options 'Créer session', 'Rejoindre session'.",
            "priorite": "haute"
        },
        {
            "id": "US002",
            "titre": "Gestion des sessions",
            "description": "Permettre au Scrum Master de créer une session avec pseudo et nombre de joueurs.",
            "priorite": "haute"
        }
    ]
}</pre>
            </section>

            <section class="rule-section">
                <h4>Conseils pratiques</h4>
                <ul>
                    <li>Votez selon votre propre jugement, pas celui des autres.</li>
                    <li>Discutez des écarts importants entre les votes.</li>
                    <li>Utilisez le chat pour clarifier les points si nécessaire.</li>
                    <li>Prenez des pauses si nécessaire (carte <i class="fas fa-coffee"></i>).</li>
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