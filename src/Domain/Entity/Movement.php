<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Domain\Entity;

use InvalidArgumentException;

final readonly class Movement
{
    public int $id;
    public string $name;

    public function __construct(int $id, string $name)
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Movement id must be greater than zero.');
        }

        $normalizedName = trim($name);
        if ($normalizedName === '') {
            throw new InvalidArgumentException('Movement name cannot be empty.');
        }

        $this->id = $id;
        $this->name = $normalizedName;
    }
}
