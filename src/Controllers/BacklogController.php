<?php

namespace App\Controllers;

require_once __DIR__ . '/../Services/JsonManager.php'; 

use App\Services\JsonManager;
use PDO;

/**
 * Contrôleur de gestion du backlog et des opérations d'import/export
 * 
 * Gère l'importation et l'exportation des backlogs au format JSON,
 * ainsi que la sauvegarde des sessions complètes.
 * 
 * @package App\Controllers
 * @author Melissa Aliouche
 */
class BacklogController
{
    /**
     * Importe un backlog depuis un fichier JSON uploadé
     * 
     * Vérifie l'upload du fichier et délègue l'import au JsonManager.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session cible
     * @param array<string, mixed> $file Tableau $_FILES contenant les informations du fichier uploadé
     * @return int Nombre d'éléments importés
     * @throws \RuntimeException Si l'upload du fichier échoue
     */
    public static function importJson(PDO $pdo, int $sessionId, array $file): int
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Erreur lors de l\'upload du fichier JSON');
        }
        
        $json = file_get_contents($file['tmp_name']);
        return JsonManager::importBacklog($pdo, $sessionId, $json);
    }

    /**
     * Exporte le backlog d'une session au format JSON
     * 
     * Génère un fichier JSON téléchargeable contenant tous les éléments
     * du backlog de la session spécifiée.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session à exporter
     * @return void Envoie directement le fichier au navigateur et termine l'exécution
     */
    public static function exportJson(PDO $pdo, int $sessionId): void
    {
        $json = JsonManager::exportBacklog($pdo, $sessionId);
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="backlog_session_' . $sessionId . '_' . date('Y-m-d') . '.json"');
        echo $json;
        exit;
    }

    /**
     * Sauvegarde complète d'une session au format JSON
     * 
     * Génère un fichier JSON téléchargeable contenant toutes les données
     * de la session (backlog, votes, résultats, etc.) avec horodatage.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     * @param int $sessionId Identifiant de la session à sauvegarder
     * @return void Envoie directement le fichier au navigateur et termine l'exécution
     */
    public static function saveSession(PDO $pdo, int $sessionId): void
    {
        $json = JsonManager::saveSession($pdo, $sessionId);
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="session_save_' . $sessionId . '_' . date('Y-m-d_His') . '.json"');
        echo $json;
        exit;
    }
}