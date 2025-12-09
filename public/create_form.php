<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Créer une session - Planning Poker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="form-bg">

<div class="form-card">
    <h2>Créer une session</h2>

    <form method="post" action="create_session.php">

        <label>Nom de la session</label>
        <input type="text" name="session_name" required>

        <label>Pseudo (Scrum Master)</label>
        <input type="text" name="pseudo" required>

        <label>Nombre max de joueurs</label>
        <input type="number" name="max_players" value="10" min="2" max="20">

        <label>Règle de vote</label>
        <select name="vote_rule">
            <option value="strict">Unanimité</option>
            <option value="moyenne">Moyenne</option>
            <option value="mediane">Médiane</option>
            <option value="majorite_absolue">Majorité absolue</option>
            <option value="majorite_relative">Majorité relative</option>
        </select>

        <button type="submit" class="btn">Créer</button>
        <a href="index.php" class="btn-back">Retour</a>

    </form>
</div>

</body>
</html>
