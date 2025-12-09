<?php
$config = require __DIR__ . '/config.php';

function getPDO(): PDO {
    static $pdo = null;
    global $config;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . $config['db_host'] .
               ';port=' . $config['db_port'] .
               ';dbname=' . $config['db_name'] .
               ';charset=utf8mb4';
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
