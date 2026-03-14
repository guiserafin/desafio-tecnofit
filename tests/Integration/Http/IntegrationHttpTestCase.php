<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class IntegrationHttpTestCase extends TestCase
{
    private static ?int $serverPort = null;
    /**
     * @var resource|null
     */
    private static $serverProcess = null;

    private static string $serverLogPath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::bootstrapTestDatabase();
        self::startHttpServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopHttpServer();

        parent::tearDownAfterClass();
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string, json: array<string, mixed>}
     */
    protected function requestJson(string $method, string $path): array
    {
        $context = stream_context_create(
            [
                'http' => [
                    'method' => strtoupper($method),
                    'ignore_errors' => true,
                    'timeout' => 10,
                ],
            ]
        );

        $url = self::baseUrl() . $path;
        $body = file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException(sprintf('Failed to execute HTTP request to %s.', $url));
        }

        if (!isset($http_response_header) || !is_array($http_response_header)) {
            throw new RuntimeException('HTTP response headers are not available.');
        }

        $responseHeaders = self::normalizeHeaders($http_response_header);
        $statusCode = self::extractStatusCode($http_response_header);

        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new RuntimeException(sprintf('Response body is not valid JSON: %s', $body));
        }

        return [
            'status' => $statusCode,
            'headers' => $responseHeaders,
            'body' => $body,
            'json' => $json,
        ];
    }

    private static function startHttpServer(): void
    {
        $phpBinary = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $projectRoot = self::projectRoot();
        $publicPath = $projectRoot . '/public';

        self::$serverPort = self::findAvailablePort();
        self::$serverLogPath = sys_get_temp_dir() . '/tecnofit_integration_server_' . self::$serverPort . '.log';

        $command = [
            $phpBinary,
            '-S',
            sprintf('127.0.0.1:%d', self::$serverPort),
            '-t',
            $publicPath,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', self::$serverLogPath, 'a'],
            2 => ['file', self::$serverLogPath, 'a'],
        ];

        $environment = [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'DB_HOST' => self::env('DB_HOST', '127.0.0.1'),
            'DB_PORT' => self::env('DB_PORT', '3306'),
            'DB_DATABASE' => self::env('DB_DATABASE', 'tecnofit_test'),
            'DB_USERNAME' => self::env('DB_USERNAME', 'tecnofit_user'),
            'DB_PASSWORD' => self::env('DB_PASSWORD', 'secret'),
        ];

        self::$serverProcess = proc_open(
            $command,
            $descriptors,
            $pipes,
            $projectRoot,
            $environment
        );

        if (!is_resource(self::$serverProcess)) {
            throw new RuntimeException('Failed to start PHP built-in server for integration tests.');
        }

        self::waitForServerToBeReady();
    }

    private static function stopHttpServer(): void
    {
        if (!is_resource(self::$serverProcess)) {
            return;
        }

        proc_terminate(self::$serverProcess);
        proc_close(self::$serverProcess);
        self::$serverProcess = null;
    }

    private static function waitForServerToBeReady(): void
    {
        if (self::$serverPort === null) {
            throw new RuntimeException('HTTP test server port is not configured.');
        }

        $attempts = 0;
        while ($attempts < 50) {
            $connection = @fsockopen('127.0.0.1', self::$serverPort, $errno, $error, 0.1);
            if (is_resource($connection)) {
                fclose($connection);

                return;
            }

            usleep(100_000);
            ++$attempts;
        }

        $logExcerpt = '';
        if (is_file(self::$serverLogPath)) {
            $logExcerpt = (string) file_get_contents(self::$serverLogPath);
        }

        throw new RuntimeException(
            sprintf(
                "PHP built-in server did not become ready on port %d.\nServer log:\n%s",
                self::$serverPort,
                $logExcerpt
            )
        );
    }

    private static function bootstrapTestDatabase(): void
    {
        $pdo = self::createPdoForTestDatabase();

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('DROP TABLE IF EXISTS personal_record');
        $pdo->exec('DROP TABLE IF EXISTS movement');
        $pdo->exec('DROP TABLE IF EXISTS `user`');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        self::executeSqlFile($pdo, self::projectRoot() . '/database/migrations/001_create_tables.sql');
        self::executeSqlFile($pdo, self::projectRoot() . '/database/migrations/002_populate_tables.sql');
    }

    private static function createPdoForTestDatabase(): PDO
    {
        $host = self::env('DB_HOST', '127.0.0.1');
        $port = self::env('DB_PORT', '3306');
        $database = self::env('DB_DATABASE', 'tecnofit_test');
        $username = self::env('DB_USERNAME', 'tecnofit_user');
        $password = self::env('DB_PASSWORD', 'secret');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        return new PDO(
            $dsn,
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    private static function executeSqlFile(PDO $pdo, string $filePath): void
    {
        $content = file_get_contents($filePath);
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException(sprintf('SQL file is empty or unreadable: %s', $filePath));
        }

        $statement = '';
        $lines = preg_split('/\R/', $content) ?: [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '' || str_starts_with($trimmedLine, '--')) {
                continue;
            }

            $statement .= $line . PHP_EOL;

            if (str_ends_with(rtrim($line), ';')) {
                $pdo->exec($statement);
                $statement = '';
            }
        }

        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }

    /**
     * @param array<int, string> $rawHeaders
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $rawHeaders): array
    {
        $headers = [];

        foreach ($rawHeaders as $headerLine) {
            $separatorPosition = strpos($headerLine, ':');
            if ($separatorPosition === false) {
                continue;
            }

            $name = strtolower(trim(substr($headerLine, 0, $separatorPosition)));
            $value = trim(substr($headerLine, $separatorPosition + 1));
            $headers[$name] = $value;
        }

        return $headers;
    }

    /**
     * @param array<int, string> $rawHeaders
     */
    private static function extractStatusCode(array $rawHeaders): int
    {
        $statusLine = $rawHeaders[0] ?? '';
        if (!is_string($statusLine) || $statusLine === '') {
            throw new RuntimeException('HTTP status line is missing in response headers.');
        }

        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) !== 1) {
            throw new RuntimeException(sprintf('Unable to parse HTTP status code from line: %s', $statusLine));
        }

        return (int) $matches[1];
    }

    private static function findAvailablePort(): int
    {
        for ($port = 18080; $port <= 18180; ++$port) {
            $connection = @fsockopen('127.0.0.1', $port);
            if (is_resource($connection)) {
                fclose($connection);

                continue;
            }

            return $port;
        }

        throw new RuntimeException('No available port found for HTTP integration server.');
    }

    private static function baseUrl(): string
    {
        if (self::$serverPort === null) {
            throw new RuntimeException('HTTP test server is not initialized.');
        }

        return sprintf('http://127.0.0.1:%d', self::$serverPort);
    }

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $default;
    }
}
