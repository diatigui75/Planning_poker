<?php
/**
 * Bootstrap file for PHPUnit tests
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$testConfig = [
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_port' => getenv('DB_PORT') ?: 3307,
    'db_name' => getenv('DB_NAME') ?: 'planning_poker_test',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: 'melissa',
    'base_url' => '/Planning_poker/public',
];

// Create config file for tests if it doesn't exist
$configPath = __DIR__ . '/../config/config.test.php';
if (!file_exists($configPath)) {
    file_put_contents($configPath, '<?php return ' . var_export($testConfig, true) . ';');
}

// Set timezone
date_default_timezone_set('UTC');

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');