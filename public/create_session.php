<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Controllers/SessionController.php';

use App\Controllers\SessionController;

$pdo = getPDO();
SessionController::create($pdo);