<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Infrastructure\Http;

use InvalidArgumentException;
use Tecnofit\MovementRanking\Domain\Exception\MovementNotFoundException;
use Throwable;

final class ErrorMapper
{
    /**
     * @var array<class-string<Throwable>, int>
     */
    private const STATUS_CODE_MAP = [
        MovementNotFoundException::class => 404,
        InvalidArgumentException::class => 400,
    ];

    private function __construct()
    {
    }

    public static function toStatusCode(Throwable $throwable): int
    {
        foreach (self::STATUS_CODE_MAP as $throwableClass => $statusCode) {
            if ($throwable instanceof $throwableClass) {
                return $statusCode;
            }
        }

        return 500;
    }

    /**
     * @return array{error: string}
     */
    public static function toPayload(Throwable $throwable, bool $isProduction, bool $isDebug): array
    {
        $statusCode = self::toStatusCode($throwable);

        if ($statusCode === 404) {
            return ['error' => 'Movement not found'];
        }

        if ($statusCode === 400) {
            return ['error' => 'Bad Request'];
        }

        if (!$isProduction || $isDebug) {
            $message = trim($throwable->getMessage());
            if ($message !== '') {
                return ['error' => $message];
            }
        }

        return ['error' => 'Internal Server Error'];
    }
}
