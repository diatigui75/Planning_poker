<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Controllers/BacklogController.php';
require_once __DIR__ . '/../src/Models/UserStory.php';



use App\Controllers\BacklogController;

session_start();
if (!isset($_SESSION['session_id'])) {
    header('Location: index.php');
    exit;
}
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        BacklogController::importJson($pdo, $_SESSION['session_id'], $_FILES['backlog']);
        header('Location: vote.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Importer backlog</title>
</head>
<body>
<h1>Importer backlog JSON</h1>
<?php if (!empty($error)): ?>
    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="backlog" accept="application/json" required>
    <button type="submit">Importer</button>
</form>
<p><a href="vote.php">Retour</a></p>
</body>
</html>
