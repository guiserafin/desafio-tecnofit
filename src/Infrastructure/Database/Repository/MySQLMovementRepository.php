<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Infrastructure\Database\Repository;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Tecnofit\MovementRanking\Domain\Entity\Movement;
use Tecnofit\MovementRanking\Domain\Repository\MovementRepositoryInterface;
use Tecnofit\MovementRanking\Domain\Support\MovementIdentifierNormalizer;

final readonly class MySQLMovementRepository implements MovementRepositoryInterface
{
    public function __construct(
        private PDO $connection
    ) {
    }

    public function findMovementByIdentifier(int|string $identifier): ?Movement
    {
        $normalizedIdentifier = MovementIdentifierNormalizer::normalize($identifier);
        if (is_int($normalizedIdentifier)) {
            return $this->findMovementById($normalizedIdentifier);
        }

        return $this->findMovementByName($normalizedIdentifier);
    }

    public function getMovementRankingByMovementId(int $movementId): array
    {
        if ($movementId <= 0) {
            throw new InvalidArgumentException('Movement id must be greater than zero.');
        }

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                DENSE_RANK() OVER (ORDER BY pr_max.personal_record DESC) AS position,
                u.name AS user,
                pr_max.personal_record,
                pr_max.record_date
            FROM (
                SELECT
                    pr.user_id,
                    MAX(pr.value) AS personal_record,
                    SUBSTRING_INDEX(
                        GROUP_CONCAT(pr.date ORDER BY pr.value DESC, pr.date DESC),
                        ',',
                        1
                    ) AS record_date
                FROM personal_record pr
                WHERE pr.movement_id = :movement_id
                GROUP BY pr.user_id
            ) AS pr_max
            JOIN `user` u ON u.id = pr_max.user_id
            ORDER BY position ASC, u.name ASC
            SQL
        );

        $statement->bindValue(':movement_id', $movementId, PDO::PARAM_INT);

        $statement->execute();

        $rows = $statement->fetchAll();

        if ($rows === false || $rows === []) {
            return [];
        }

        $ranking = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new RuntimeException(sprintf('Unexpected ranking row type at index %d.', $index));
            }

            $ranking[] = $this->hydrateRankingRow($row, $index);
        }

        return $ranking;
    }

    private function findMovementById(int $id): ?Movement
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Movement id must be greater than zero.');
        }

        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT id, name
            FROM movement
            WHERE id = :id
            LIMIT 1
            SQL
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException('Unexpected movement row type for id query.');
        }

        return $this->hydrateMovementRow($row);
    }

    private function findMovementByName(string $name): ?Movement
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT id, name
            FROM movement
            WHERE LOWER(name) = LOWER(:name)
            LIMIT 1
            SQL
        );
        $statement->bindValue(':name', $name, PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException('Unexpected movement row type for name query.');
        }

        return $this->hydrateMovementRow($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateMovementRow(array $row): Movement
    {
        $id = $row['id'] ?? null;
        $name = $row['name'] ?? null;

        if (!is_numeric($id)) {
            throw new RuntimeException('Invalid movement id returned by database.');
        }

        if (!is_string($name)) {
            throw new RuntimeException('Invalid movement name returned by database.');
        }

        return new Movement((int) $id, $name);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{
     *     position: int,
     *     user: string,
     *     personal_record: float,
     *     record_date: DateTimeImmutable
     * }
     */
    private function hydrateRankingRow(array $row, int $index): array
    {
        $position = $row['position'] ?? null;
        $user = $row['user'] ?? null;
        $personalRecord = $row['personal_record'] ?? null;
        $recordDate = $row['record_date'] ?? null;

        if (!is_numeric($position) || (int) $position <= 0) {
            throw new RuntimeException(sprintf('Invalid ranking position at index %d.', $index));
        }

        if (!is_string($user) || trim($user) === '') {
            throw new RuntimeException(sprintf('Invalid ranking user at index %d.', $index));
        }

        if (!is_numeric($personalRecord)) {
            throw new RuntimeException(sprintf('Invalid ranking personal_record at index %d.', $index));
        }

        if (!is_string($recordDate) || trim($recordDate) === '') {
            throw new RuntimeException(sprintf('Invalid ranking record_date at index %d.', $index));
        }

        $recordDateAsDateTime = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $recordDate,
            new DateTimeZone('UTC')
        );
        if (!$recordDateAsDateTime instanceof DateTimeImmutable) {
            throw new RuntimeException(sprintf('Unable to parse ranking record_date at index %d.', $index));
        }

        return [
            'position' => (int) $position,
            'user' => trim($user),
            'personal_record' => (float) $personalRecord,
            'record_date' => $recordDateAsDateTime,
        ];
    }
}
