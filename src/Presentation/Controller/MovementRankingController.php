<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Presentation\Controller;

use DateTimeInterface;
use RuntimeException;
use Tecnofit\MovementRanking\Application\DTO\MovementRankingRequestDTO;
use Tecnofit\MovementRanking\Application\UseCase\GetMovementRankingUseCase;
use Tecnofit\MovementRanking\Infrastructure\Http\Response;

final readonly class MovementRankingController
{
    public function __construct(
        private GetMovementRankingUseCase $getMovementRankingUseCase
    ) {
    }

    public function getRanking(string $identifier): Response
    {
        $useCaseResponse = $this->getMovementRankingUseCase->execute(
            new MovementRankingRequestDTO($identifier)
        );

        $ranking = array_map(
            static function (array $item): array {
                $recordDate = $item['record_date'] ?? null;
                if (!$recordDate instanceof DateTimeInterface) {
                    throw new RuntimeException('Ranking record_date must be a DateTimeInterface instance.');
                }

                return [
                    'position' => $item['position'],
                    'user' => $item['user'],
                    'personal_record' => $item['personal_record'],
                    'record_date' => $recordDate->format(DATE_ATOM),
                ];
            },
            $useCaseResponse->ranking
        );

        return Response::json(
            [
                'movement' => $useCaseResponse->movement,
                'ranking' => $ranking,
            ],
            200
        );
    }
}
