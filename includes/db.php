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

    // #region agent log
    $__dbgPayload = [
        'sessionId' => '0403b0',
        'runId' => 'post-fix',
        'hypothesisId' => 'A',
        'location' => 'includes/db.php:get_db',
        'message' => 'DB connect attempt',
        'data' => [
            'host' => (string) ($config['host'] ?? ''),
            'dbname' => (string) ($config['dbname'] ?? ''),
            'user' => (string) ($config['user'] ?? ''),
            'passEmpty' => (($config['pass'] ?? '') === ''),
            'userMissingCpanelPrefix' => !str_starts_with((string) ($config['user'] ?? ''), 'cpaneluser_'),
            'dbnameMissingCpanelPrefix' => !str_starts_with((string) ($config['dbname'] ?? ''), 'cpaneluser_'),
            'docRootHint' => (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''),
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ];
    @file_put_contents(dirname(__DIR__) . '/debug-0403b0.log', json_encode($__dbgPayload) . "\n", FILE_APPEND);
    // #endregion

    try {
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        // #region agent log
        $__dbgFail = [
            'sessionId' => '0403b0',
            'runId' => 'post-fix',
            'hypothesisId' => 'B',
            'location' => 'includes/db.php:get_db:catch',
            'message' => 'DB connect failed',
            'data' => [
                'errorPrefix' => substr($e->getMessage(), 0, 120),
                'user' => (string) ($config['user'] ?? ''),
                'dbname' => (string) ($config['dbname'] ?? ''),
                'passEmpty' => (($config['pass'] ?? '') === ''),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ];
        @file_put_contents(dirname(__DIR__) . '/debug-0403b0.log', json_encode($__dbgFail) . "\n", FILE_APPEND);
        // #endregion
        if (
            str_contains($e->getMessage(), '1045')
            && ($config['user'] ?? '') === 'root'
            && ($config['pass'] ?? '') === ''
        ) {
            throw new RuntimeException(
                'Database login failed: includes/db.config.php is using local XAMPP credentials (root, no password). '
                . 'On cPanel, create a MySQL database and user, then edit includes/db.config.php on the server with those credentials. '
                . 'Do not upload your local db.config.php.',
                0,
                $e
            );
        }
        if (
            str_contains($e->getMessage(), '1045')
            && str_contains((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), 'cpaneluser')
        ) {
            throw new RuntimeException(
                'Database login failed for user "' . ($config['user'] ?? '') . '" on database "' . ($config['dbname'] ?? '') . '". '
                . 'On cPanel, dbname and user must use the full prefixed names (e.g. cpaneluser_dbname and cpaneluser_dbuser). '
                . 'Edit includes/db.config.php on the server only — do not upload your local XAMPP db.config.php.',
                0,
                $e
            );
        }
        throw $e;
    }

    // #region agent log
    $__dbgOk = [
        'sessionId' => '0403b0',
        'runId' => 'post-fix',
        'hypothesisId' => 'C',
        'location' => 'includes/db.php:get_db:ok',
        'message' => 'DB connect succeeded',
        'data' => [
            'user' => (string) ($config['user'] ?? ''),
            'dbname' => (string) ($config['dbname'] ?? ''),
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ];
    @file_put_contents(dirname(__DIR__) . '/debug-0403b0.log', json_encode($__dbgOk) . "\n", FILE_APPEND);
    // #endregion

    return $pdo;
}
