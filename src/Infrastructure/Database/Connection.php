<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Infrastructure\Database;

use PDO;
use PDOException;
use RuntimeException;
use Tecnofit\MovementRanking\Infrastructure\Configuration\Environment;

final class Connection
{
    private function __construct()
    {
    }

    public static function createFromEnvironment(): PDO
    {
        $host = Environment::require('DB_HOST');
        $port = Environment::require('DB_PORT');
        $database = Environment::require('DB_DATABASE');
        $username = Environment::require('DB_USERNAME');
        $password = Environment::require('DB_PASSWORD', allowEmpty: true);

        if (!ctype_digit($port) || (int) $port <= 0) {
            throw new RuntimeException('Environment variable DB_PORT must be a positive integer.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            (int) $port,
            $database
        );

        try {
            return new PDO(
                dsn: $dsn,
                username: $username,
                password: $password,
                options: [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed.', 0, $exception);
        }
    }
}
