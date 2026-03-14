<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Infrastructure\Configuration;

use RuntimeException;

final class Environment
{
    private function __construct()
    {
    }

    public static function require(string $name, bool $allowEmpty = false): string
    {
        $value = self::resolve($name);
        if ($value === null) {
            throw new RuntimeException(sprintf('Environment variable %s is required.', $name));
        }

        if (!$allowEmpty && trim($value) === '') {
            throw new RuntimeException(sprintf('Environment variable %s cannot be empty.', $name));
        }

        return $value;
    }

    public static function get(string $name, string $default): string
    {
        $value = self::resolve($name);
        if ($value === null || trim($value) === '') {
            return $default;
        }

        return $value;
    }

    public static function getBoolean(string $name, bool $default): bool
    {
        $rawValue = self::resolve($name);
        if ($rawValue === null || trim($rawValue) === '') {
            return $default;
        }

        return in_array(
            strtolower(trim($rawValue)),
            ['1', 'true', 'on', 'yes'],
            true
        );
    }

    private static function resolve(string $name): ?string
    {
        $environmentCandidates = [
            $_ENV[$name] ?? null,
            $_SERVER[$name] ?? null,
            getenv($name),
        ];

        foreach ($environmentCandidates as $candidate) {
            if (is_string($candidate)) {
                return $candidate;
            }
        }

        $dotEnvValues = self::loadDotEnvValues();

        return $dotEnvValues[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function loadDotEnvValues(): array
    {
        static $cachedValues = null;

        if (is_array($cachedValues)) {
            return $cachedValues;
        }

        $dotEnvPath = dirname(__DIR__, 3) . '/.env';
        if (!is_file($dotEnvPath)) {
            $cachedValues = [];

            return $cachedValues;
        }

        $lines = file($dotEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $cachedValues = [];

            return $cachedValues;
        }

        $values = [];

        foreach ($lines as $line) {
            $normalizedLine = trim($line);
            if ($normalizedLine === '' || str_starts_with($normalizedLine, '#')) {
                continue;
            }

            $separatorPosition = strpos($normalizedLine, '=');
            if ($separatorPosition === false) {
                continue;
            }

            $key = trim(substr($normalizedLine, 0, $separatorPosition));
            if ($key === '') {
                continue;
            }

            $value = trim(substr($normalizedLine, $separatorPosition + 1));
            $values[$key] = self::stripWrappingQuotes($value);
        }

        $cachedValues = $values;

        return $cachedValues;
    }

    private static function stripWrappingQuotes(string $value): string
    {
        $length = strlen($value);
        if ($length < 2) {
            return $value;
        }

        $startsWithSingleQuote = $value[0] === "'";
        $endsWithSingleQuote = $value[$length - 1] === "'";
        $startsWithDoubleQuote = $value[0] === '"';
        $endsWithDoubleQuote = $value[$length - 1] === '"';

        if (($startsWithSingleQuote && $endsWithSingleQuote) || ($startsWithDoubleQuote && $endsWithDoubleQuote)) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
