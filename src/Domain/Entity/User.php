<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Domain\Entity;

use InvalidArgumentException;

final readonly class User
{
    public int $id;
    public string $name;

    public function __construct(int $id, string $name)
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('User id must be greater than zero.');
        }

        $normalizedName = trim($name);
        if ($normalizedName === '') {
            throw new InvalidArgumentException('User name cannot be empty.');
        }

        $this->id = $id;
        $this->name = $normalizedName;
    }
}
