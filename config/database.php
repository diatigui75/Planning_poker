<?php

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        // Déterminer l'environnement
        $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'prod';
        
        // Choisir le fichier de config
        $configFile = ($env === 'testing')
            ? __DIR__ . '/config.test.php'
            : __DIR__ . '/config.php';
        
        // Vérifier l'existence
        if (!file_exists($configFile)) {
            throw new RuntimeException("Fichier de configuration introuvable : $configFile");
        }
        
        // Charger la config
        $config = require $configFile;
        
        // Vérifier la validité
        if (!is_array($config)) {
            throw new RuntimeException("Configuration DB invalide dans $configFile");
        }
        
        // Créer la connexion PDO
        $dsn = 'mysql:host=' . $config['db_host']
             . ';port=' . $config['db_port']
             . ';dbname=' . $config['db_name']
             . ';charset=utf8mb4';

        try {
            $pdo = new PDO(
                $dsn,
                $config['db_user'],
                $config['db_pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Erreur de connexion à la base de données ($configFile): " . $e->getMessage()
            );
        }
    }

    return $pdo;
}