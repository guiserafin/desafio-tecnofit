<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Infrastructure\Http;

use InvalidArgumentException;

final readonly class Request
{
    public string $method;
    public string $path;

    public function __construct(string $method, string $path)
    {
        $normalizedMethod = strtoupper(trim($method));
        if ($normalizedMethod === '') {
            throw new InvalidArgumentException('HTTP method cannot be empty.');
        }

        $this->method = $normalizedMethod;
        $this->path = self::normalizePath($path);
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        if (!is_string($method)) {
            $method = 'GET';
        }

        if (!is_string($requestUri)) {
            $requestUri = '/';
        }

        return new self($method, $requestUri);
    }

    private static function normalizePath(string $path): string
    {
        $parsedPath = parse_url($path, PHP_URL_PATH);
        if (!is_string($parsedPath) || $parsedPath === '') {
            return '/';
        }

        $normalizedPath = '/' . ltrim($parsedPath, '/');
        if ($normalizedPath !== '/') {
            $normalizedPath = rtrim($normalizedPath, '/');
        }

        return $normalizedPath === '' ? '/' : $normalizedPath;
    }
}
