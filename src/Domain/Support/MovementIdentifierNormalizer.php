<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Domain\Support;

use InvalidArgumentException;

final class MovementIdentifierNormalizer
{
    private function __construct()
    {
    }

    public static function normalize(int|string $identifier): int|string
    {
        if (is_int($identifier)) {
            self::assertPositiveIdentifier($identifier);

            return $identifier;
        }

        $normalizedIdentifier = trim($identifier);
        if ($normalizedIdentifier === '') {
            throw new InvalidArgumentException('Movement identifier cannot be empty.');
        }

        if (ctype_digit($normalizedIdentifier)) {
            $parsedIdentifier = (int) $normalizedIdentifier;
            self::assertPositiveIdentifier($parsedIdentifier);

            return $parsedIdentifier;
        }

        return $normalizedIdentifier;
    }

    private static function assertPositiveIdentifier(int $identifier): void
    {
        if ($identifier <= 0) {
            throw new InvalidArgumentException('Movement identifier must be greater than zero.');
        }
    }
}
