<?php
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Controllers/BacklogController.php';
require_once __DIR__ . '/../src/Services/JsonManager.php';
require_once __DIR__ . '/../src/Models/UserStory.php';
require_once __DIR__ . '/../src/Models/Session.php';



use App\Controllers\BacklogController;

session_start();
if (!isset($_SESSION['session_id'])) {
    header('Location: index.php');
    exit;
}
$pdo = getPDO();
BacklogController::exportJson($pdo, $_SESSION['session_id']);
