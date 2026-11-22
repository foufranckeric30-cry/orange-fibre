<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$envFile = $root . '/.env';

if (!file_exists($envFile)) {
    throw new RuntimeException('Fichier .env introuvable.');
}

$env = parse_ini_file($envFile, false, INI_SCANNER_RAW);

if ($env === false) {
    throw new RuntimeException('Impossible de lire .env.');
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '5432';
$name = $env['DB_NAME'] ?? 'orange_fibre';
$user = $env['DB_USER'] ?? 'orange_app';
$password = $env['DB_PASSWORD'] ?? '';

if ($password === '' || $password === 'TON_MOT_DE_PASSE_POSTGRESQL') {
    throw new RuntimeException('Mot de passe PostgreSQL non configuré.');
}

$dsn = "pgsql:host=$host;port=$port;dbname=$name";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    throw new RuntimeException('Connexion PostgreSQL impossible.');
}

return $pdo;
