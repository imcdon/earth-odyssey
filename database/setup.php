<?php
/*
 * setup.php - One-time CLI setup: create tables and seed sample data.
 * Run: php database/setup.php
 */
$config = require __DIR__ . '/../includes/db.config.php';

$dsn = sprintf('mysql:host=%s;charset=%s', $config['host'], $config['charset']);
$pdo = new PDO($dsn, $config['user'], $config['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

foreach (['schema.sql', 'seed.sql'] as $file) {
    $sql = file_get_contents(__DIR__ . '/' . $file);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
    echo "Ran {$file}\n";
}

echo "Database ready.\n";
