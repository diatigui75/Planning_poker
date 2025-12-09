<?php

namespace App\Controllers;

require_once __DIR__ . '/../Services/JsonManager.php'; 

use App\Services\JsonManager;
use PDO;

class BacklogController
{
    public static function importJson(PDO $pdo, int $sessionId, array $file): int
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Erreur lors de l\'upload du fichier JSON');
        }
        
        $json = file_get_contents($file['tmp_name']);
        return JsonManager::importBacklog($pdo, $sessionId, $json);
    }

    public static function exportJson(PDO $pdo, int $sessionId): void
    {
        $json = JsonManager::exportBacklog($pdo, $sessionId);
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="backlog_session_' . $sessionId . '_' . date('Y-m-d') . '.json"');
        echo $json;
        exit;
    }

    public static function saveSession(PDO $pdo, int $sessionId): void
    {
        $json = JsonManager::saveSession($pdo, $sessionId);
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="session_save_' . $sessionId . '_' . date('Y-m-d_His') . '.json"');
        echo $json;
        exit;
    }
}