<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Application\UseCase;

use Tecnofit\MovementRanking\Application\DTO\MovementRankingRequestDTO;
use Tecnofit\MovementRanking\Application\DTO\MovementRankingResponseDTO;
use Tecnofit\MovementRanking\Domain\Exception\MovementNotFoundException;
use Tecnofit\MovementRanking\Domain\Repository\MovementRepositoryInterface;

final readonly class GetMovementRankingUseCase
{
    public function __construct(
        private MovementRepositoryInterface $movementRepository
    ) {
    }

    public function execute(MovementRankingRequestDTO $request): MovementRankingResponseDTO
    {
        $movement = $this->movementRepository->findMovementByIdentifier($request->identifier);
        if ($movement === null) {
            throw new MovementNotFoundException();
        }

        $ranking = $this->movementRepository->getMovementRankingByMovementId($movement->id);

        return new MovementRankingResponseDTO(
            movement: $movement->name,
            ranking: $ranking
        );
    }
}
