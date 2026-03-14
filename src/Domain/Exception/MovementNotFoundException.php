<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Domain\Exception;

use RuntimeException;
use Throwable;

final class MovementNotFoundException extends RuntimeException
{
    public function __construct(
        string $message = 'Movement not found',
        int $code = 404,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
