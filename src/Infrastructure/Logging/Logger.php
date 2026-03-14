<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Infrastructure\Logging;

use Throwable;

final class Logger
{
    public function logRequest(string $method, string $uri, int $statusCode, float $durationMs): void
    {
        error_log(
            sprintf(
                '[request] method=%s uri=%s status=%d duration_ms=%.2f',
                strtoupper($method),
                $uri,
                $statusCode,
                $durationMs
            )
        );
    }

    public function logServerError(Throwable $throwable, int $statusCode): void
    {
        if ($statusCode < 500) {
            return;
        }

        error_log(
            sprintf(
                "[error] status=%d type=%s message=%s file=%s line=%d\n%s",
                $statusCode,
                $throwable::class,
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine(),
                $throwable->getTraceAsString()
            )
        );
    }
}
