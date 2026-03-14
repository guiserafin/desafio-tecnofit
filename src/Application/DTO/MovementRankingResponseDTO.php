<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Application\DTO;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MovementRankingResponseDTO
{
    public string $movement;

    /**
     * @var list<array{
     *     position: int,
     *     user: string,
     *     personal_record: float,
     *     record_date: DateTimeImmutable
     * }>
     */
    public array $ranking;

    /**
     * @param list<array{
     *     position: int,
     *     user: string,
     *     personal_record: float|int,
     *     record_date: DateTimeImmutable
     * }> $ranking
     */
    public function __construct(string $movement, array $ranking)
    {
        $normalizedMovement = trim($movement);
        if ($normalizedMovement === '') {
            throw new InvalidArgumentException('Movement name cannot be empty.');
        }

        $this->movement = $normalizedMovement;
        $this->ranking = self::normalizeRanking($ranking);
    }

    /**
     * @param list<array{
     *     position: int,
     *     user: string,
     *     personal_record: float|int,
     *     record_date: DateTimeImmutable
     * }> $ranking
     *
     * @return list<array{
     *     position: int,
     *     user: string,
     *     personal_record: float,
     *     record_date: DateTimeImmutable
     * }>
     */
    private static function normalizeRanking(array $ranking): array
    {
        $normalized = [];

        foreach ($ranking as $index => $item) {
            if (!isset($item['position'], $item['user'], $item['personal_record'], $item['record_date'])) {
                throw new InvalidArgumentException(
                    sprintf('Ranking item at index %d is missing required fields.', $index)
                );
            }

            $position = $item['position'];
            $user = $item['user'];
            $personalRecord = $item['personal_record'];
            $recordDate = $item['record_date'];

            if (!is_int($position) || $position <= 0) {
                throw new InvalidArgumentException(sprintf('Ranking position at index %d must be a positive integer.', $index));
            }

            if (!is_string($user) || trim($user) === '') {
                throw new InvalidArgumentException(sprintf('Ranking user at index %d must be a non-empty string.', $index));
            }

            if (!is_int($personalRecord) && !is_float($personalRecord)) {
                throw new InvalidArgumentException(sprintf('Ranking personal_record at index %d must be numeric.', $index));
            }

            if (!$recordDate instanceof DateTimeImmutable) {
                throw new InvalidArgumentException(
                    sprintf('Ranking record_date at index %d must be an instance of DateTimeImmutable.', $index)
                );
            }

            $normalized[] = [
                'position' => $position,
                'user' => trim($user),
                'personal_record' => (float) $personalRecord,
                'record_date' => $recordDate,
            ];
        }

        return $normalized;
    }
}
