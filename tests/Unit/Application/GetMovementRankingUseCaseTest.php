<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tecnofit\MovementRanking\Application\DTO\MovementRankingRequestDTO;
use Tecnofit\MovementRanking\Application\UseCase\GetMovementRankingUseCase;
use Tecnofit\MovementRanking\Domain\Entity\Movement;
use Tecnofit\MovementRanking\Domain\Exception\MovementNotFoundException;
use Tecnofit\MovementRanking\Domain\Repository\MovementRepositoryInterface;

final class GetMovementRankingUseCaseTest extends TestCase
{
    public function testU01RankingSemEmpatesRetornaPosicoesCorretas(): void
    {
        $movement = new Movement(1, 'Deadlift');
        $ranking = [
            [
                'position' => 1,
                'user' => 'Jose',
                'personal_record' => 190.0,
                'record_date' => new DateTimeImmutable('2021-01-06T00:00:00+00:00'),
            ],
            [
                'position' => 2,
                'user' => 'Joao',
                'personal_record' => 180.0,
                'record_date' => new DateTimeImmutable('2021-01-02T00:00:00+00:00'),
            ],
            [
                'position' => 3,
                'user' => 'Paulo',
                'personal_record' => 170.0,
                'record_date' => new DateTimeImmutable('2021-01-01T00:00:00+00:00'),
            ],
        ];

        $repository = $this->createMock(MovementRepositoryInterface::class);
        $repository
            ->expects($this->once())
            ->method('findMovementByIdentifier')
            ->with(1)
            ->willReturn($movement);
        $repository
            ->expects($this->once())
            ->method('getMovementRankingByMovementId')
            ->with(1)
            ->willReturn($ranking);

        $useCase = new GetMovementRankingUseCase($repository);
        $response = $useCase->execute(new MovementRankingRequestDTO('1'));

        self::assertSame('Deadlift', $response->movement);
        self::assertCount(3, $response->ranking);
        self::assertSame([1, 2, 3], array_column($response->ranking, 'position'));
        self::assertSame(['Jose', 'Joao', 'Paulo'], array_column($response->ranking, 'user'));
    }

    public function testU02EmpateSimplesMantemPosicoesDenseRank(): void
    {
        $movement = new Movement(2, 'Back Squat');
        $ranking = [
            [
                'position' => 1,
                'user' => 'Joao',
                'personal_record' => 130.0,
                'record_date' => new DateTimeImmutable('2021-01-03T00:00:00+00:00'),
            ],
            [
                'position' => 1,
                'user' => 'Jose',
                'personal_record' => 130.0,
                'record_date' => new DateTimeImmutable('2021-01-03T00:00:00+00:00'),
            ],
            [
                'position' => 2,
                'user' => 'Paulo',
                'personal_record' => 125.0,
                'record_date' => new DateTimeImmutable('2021-01-03T00:00:00+00:00'),
            ],
        ];

        $repository = $this->createMock(MovementRepositoryInterface::class);
        $repository
            ->expects($this->once())
            ->method('findMovementByIdentifier')
            ->with('Back Squat')
            ->willReturn($movement);
        $repository
            ->expects($this->once())
            ->method('getMovementRankingByMovementId')
            ->with(2)
            ->willReturn($ranking);

        $useCase = new GetMovementRankingUseCase($repository);
        $response = $useCase->execute(new MovementRankingRequestDTO('Back Squat'));

        self::assertSame([1, 1, 2], array_column($response->ranking, 'position'));
        self::assertSame(['Joao', 'Jose', 'Paulo'], array_column($response->ranking, 'user'));
    }

    public function testU03EmpateMultiploMantemProximaPosicaoSemSalto(): void
    {
        $movement = new Movement(3, 'Bench Press');
        $ranking = [
            [
                'position' => 1,
                'user' => 'Joao',
                'personal_record' => 100.0,
                'record_date' => new DateTimeImmutable('2021-01-01T00:00:00+00:00'),
            ],
            [
                'position' => 1,
                'user' => 'Jose',
                'personal_record' => 100.0,
                'record_date' => new DateTimeImmutable('2021-01-01T00:00:00+00:00'),
            ],
            [
                'position' => 1,
                'user' => 'Paulo',
                'personal_record' => 100.0,
                'record_date' => new DateTimeImmutable('2021-01-01T00:00:00+00:00'),
            ],
            [
                'position' => 2,
                'user' => 'Ana',
                'personal_record' => 90.0,
                'record_date' => new DateTimeImmutable('2021-01-02T00:00:00+00:00'),
            ],
        ];

        $repository = $this->createMock(MovementRepositoryInterface::class);
        $repository
            ->expects($this->once())
            ->method('findMovementByIdentifier')
            ->with('Bench Press')
            ->willReturn($movement);
        $repository
            ->expects($this->once())
            ->method('getMovementRankingByMovementId')
            ->with(3)
            ->willReturn($ranking);

        $useCase = new GetMovementRankingUseCase($repository);
        $response = $useCase->execute(new MovementRankingRequestDTO('Bench Press'));

        self::assertSame([1, 1, 1, 2], array_column($response->ranking, 'position'));
    }

    public function testU04RecordeCorretoMantemMaiorValorDoUsuario(): void
    {
        $movement = new Movement(1, 'Deadlift');
        $ranking = [
            [
                'position' => 1,
                'user' => 'Joao',
                'personal_record' => 180.0,
                'record_date' => new DateTimeImmutable('2021-01-02T00:00:00+00:00'),
            ],
        ];

        $repository = $this->createConfiguredMock(
            MovementRepositoryInterface::class,
            [
                'findMovementByIdentifier' => $movement,
                'getMovementRankingByMovementId' => $ranking,
            ]
        );

        $useCase = new GetMovementRankingUseCase($repository);
        $response = $useCase->execute(new MovementRankingRequestDTO(1));

        self::assertSame(180.0, $response->ranking[0]['personal_record']);
    }

    public function testU05DataDoRecordeCorrespondeAoRecordePessoal(): void
    {
        $movement = new Movement(1, 'Deadlift');
        $recordDate = new DateTimeImmutable('2021-01-06T00:00:00+00:00');
        $ranking = [
            [
                'position' => 1,
                'user' => 'Jose',
                'personal_record' => 190.0,
                'record_date' => $recordDate,
            ],
        ];

        $repository = $this->createConfiguredMock(
            MovementRepositoryInterface::class,
            [
                'findMovementByIdentifier' => $movement,
                'getMovementRankingByMovementId' => $ranking,
            ]
        );

        $useCase = new GetMovementRankingUseCase($repository);
        $response = $useCase->execute(new MovementRankingRequestDTO(1));

        self::assertSame($recordDate->format(DATE_ATOM), $response->ranking[0]['record_date']->format(DATE_ATOM));
    }

    public function testU06MovimentoNaoEncontradoLancaExcecao(): void
    {
        $repository = $this->createMock(MovementRepositoryInterface::class);
        $repository
            ->expects($this->once())
            ->method('findMovementByIdentifier')
            ->with(999)
            ->willReturn(null);
        $repository
            ->expects($this->never())
            ->method('getMovementRankingByMovementId');

        $useCase = new GetMovementRankingUseCase($repository);

        $this->expectException(MovementNotFoundException::class);
        $useCase->execute(new MovementRankingRequestDTO(999));
    }

    public function testU07RankingVazioQuandoMovimentoExisteSemRegistros(): void
    {
        $movement = new Movement(10, 'Strict Press');

        $repository = $this->createMock(MovementRepositoryInterface::class);
        $repository
            ->expects($this->once())
            ->method('findMovementByIdentifier')
            ->with('Strict Press')
            ->willReturn($movement);
        $repository
            ->expects($this->once())
            ->method('getMovementRankingByMovementId')
            ->with(10)
            ->willReturn([]);

        $useCase = new GetMovementRankingUseCase($repository);
        $response = $useCase->execute(new MovementRankingRequestDTO('Strict Press'));

        self::assertSame('Strict Press', $response->movement);
        self::assertSame([], $response->ranking);
    }
}
