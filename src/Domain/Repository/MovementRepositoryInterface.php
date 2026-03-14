<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Domain\Repository;

use DateTimeImmutable;
use Tecnofit\MovementRanking\Domain\Entity\Movement;

interface MovementRepositoryInterface
{
    public function findMovementByIdentifier(int|string $identifier): ?Movement;

    /**
     * @return list<array{
     *     position: int,
     *     user: string,
     *     personal_record: float,
     *     record_date: DateTimeImmutable
     * }>
     */
    public function getMovementRankingByMovementId(int $movementId): array;
}
