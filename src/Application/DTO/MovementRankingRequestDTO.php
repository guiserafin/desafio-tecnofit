<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Application\DTO;

use Tecnofit\MovementRanking\Domain\Support\MovementIdentifierNormalizer;

final readonly class MovementRankingRequestDTO
{
    public int|string $identifier;

    public function __construct(int|string $identifier)
    {
        $this->identifier = MovementIdentifierNormalizer::normalize($identifier);
    }
}
