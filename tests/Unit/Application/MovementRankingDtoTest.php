<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tecnofit\MovementRanking\Application\DTO\MovementRankingRequestDTO;
use Tecnofit\MovementRanking\Application\DTO\MovementRankingResponseDTO;

final class MovementRankingDtoTest extends TestCase
{
    public function testRequestDtoNormalizaStringNumericaParaInteiro(): void
    {
        $dto = new MovementRankingRequestDTO('001');

        self::assertSame(1, $dto->identifier);
    }

    public function testRequestDtoRemoveEspacosDoNomeDoMovimento(): void
    {
        $dto = new MovementRankingRequestDTO('  Deadlift  ');

        self::assertSame('Deadlift', $dto->identifier);
    }

    public function testRequestDtoLancaErroParaIdentificadorVazio(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MovementRankingRequestDTO('   ');
    }

    public function testResponseDtoLancaErroQuandoRankingTemShapeInvalido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MovementRankingResponseDTO(
            movement: 'Deadlift',
            ranking: [
                [
                    'position' => 1,
                    'user' => 'Jose',
                    'personal_record' => 190.0,
                ],
            ]
        );
    }

    public function testU08ResponseDtoEhImutavel(): void
    {
        $dto = new MovementRankingResponseDTO(
            movement: 'Deadlift',
            ranking: [
                [
                    'position' => 1,
                    'user' => 'Jose',
                    'personal_record' => 190.0,
                    'record_date' => new DateTimeImmutable('2021-01-06T00:00:00+00:00'),
                ],
            ]
        );

        $this->expectException(\Error::class);
        $dto->movement = 'Back Squat';
    }
}
