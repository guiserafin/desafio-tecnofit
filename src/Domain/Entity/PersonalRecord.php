<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Domain\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class PersonalRecord
{
    public int $id;
    public int $userId;
    public int $movementId;
    public float $value;
    public DateTimeImmutable $date;

    public function __construct(
        int $id,
        int $userId,
        int $movementId,
        float $value,
        DateTimeImmutable $date
    ) {
        if ($id <= 0) {
            throw new InvalidArgumentException('Personal record id must be greater than zero.');
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException('User id must be greater than zero.');
        }

        if ($movementId <= 0) {
            throw new InvalidArgumentException('Movement id must be greater than zero.');
        }

        if ($value <= 0) {
            throw new InvalidArgumentException('Personal record value must be greater than zero.');
        }

        $this->id = $id;
        $this->userId = $userId;
        $this->movementId = $movementId;
        $this->value = $value;
        $this->date = $date;
    }
}
