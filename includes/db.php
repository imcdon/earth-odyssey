<?php
/*
 * db.php - PDO MySQL connection. Loads credentials from db.config.php.
 */
function get_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configFile = __DIR__ . '/db.config.php';
    if (!file_exists($configFile)) {
        throw new RuntimeException('Missing includes/db.config.php — copy db.config.example.php');
    }

    $config = require $configFile;
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['dbname'],
        $config['charset']
    );

    try {
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        // The original exception is logged, never rethrown or chained: its stack trace can
        // include the password (PDO::__construct args) on PHP < 8.2 when errors are displayed.
        error_log('Database connection failed: ' . $e->getMessage());

        $hint = 'Check includes/db.config.php on the server (do not upload your local XAMPP copy).';
        if (str_contains($e->getMessage(), '1045')) {
            $hint = (($config['user'] ?? '') === 'root' && ($config['pass'] ?? '') === '')
                ? 'includes/db.config.php is using local XAMPP credentials (root, no password). On cPanel, use the MySQL user and database you created there.'
                : 'The MySQL login was rejected. On cPanel, the user and database names need the full account prefix, and the password must match cPanel → MySQL Databases.';
        }
        throw new RuntimeException('Database connection failed. ' . $hint);
    }

    return $pdo;
}
