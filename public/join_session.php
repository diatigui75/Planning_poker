<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../src/Controllers/SessionController.php';

use App\Controllers\SessionController;

$pdo = getPDO();
SessionController::join($pdo);
