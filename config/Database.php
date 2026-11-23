<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $envFile = dirname(__DIR__) . '/.env';

        if (!is_file($envFile)) {
            throw new RuntimeException('Fichier .env introuvable.');
        }

        $env = [];

        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim(trim($value), "\"'");
        }

        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $port = $env['DB_PORT'] ?? '5432';
        $database = $env['DB_DATABASE'] ?? $env['DB_NAME'] ?? 'orange_fibre';
        $username = $env['DB_USERNAME'] ?? $env['DB_USER'] ?? null;
        $password = $env['DB_PASSWORD'] ?? null;

        if (!$username) {
            throw new RuntimeException(
                'DB_USERNAME ou DB_USER doit être défini dans .env'
            );
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$database";

        self::$connection = new PDO(
            $dsn,
            $username,
            $password ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return self::$connection;
    }
}
