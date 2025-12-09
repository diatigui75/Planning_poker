<?php
$error = $_GET['error'] ?? '';
$errorMessages = [
    'missing_fields' => 'Tous les champs sont obligatoires',
    'session_not_found' => 'Code de session invalide',
    'session_full' => 'Cette session est complète',
    'pseudo_taken' => 'Ce pseudo est déjà utilisé dans cette session',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejoindre une session - Planning Poker</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>

<body class="form-bg">

<div class="form-card">
    <h2>Rejoindre une session</h2>

    <?php if ($error && isset($errorMessages[$error])): ?>
        <div class="error-message">
            ⚠️ <?php echo htmlspecialchars($errorMessages[$error]); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="join_session.php">

        <label>Code de la session *</label>
        <input type="text" name="session_code" required placeholder="Ex: 5D2D3442" 
               style="text-transform: uppercase;" maxlength="8">

        <label>Votre pseudo *</label>
        <input type="text" name="pseudo" required placeholder="Ex: Jane Smith">

        <button type="submit" class="btn">Rejoindre</button>
        <a href="index.php" class="btn-back">Retour</a>

    </form>
</div>

</body>
</html>