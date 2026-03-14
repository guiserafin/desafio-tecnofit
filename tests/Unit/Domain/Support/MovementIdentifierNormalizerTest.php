<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Support;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tecnofit\MovementRanking\Domain\Support\MovementIdentifierNormalizer;

final class MovementIdentifierNormalizerTest extends TestCase
{
    public function testNormalizaIdentificadorNumericoStringParaInteiro(): void
    {
        $identifier = MovementIdentifierNormalizer::normalize('001');

        self::assertSame(1, $identifier);
    }

    public function testNormalizaNomeComEspacos(): void
    {
        $identifier = MovementIdentifierNormalizer::normalize('  Deadlift  ');

        self::assertSame('Deadlift', $identifier);
    }

    public function testMantemIdentificadorInteiroValido(): void
    {
        $identifier = MovementIdentifierNormalizer::normalize(10);

        self::assertSame(10, $identifier);
    }

    public function testLancaErroQuandoIdentificadorStringEhVazio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Movement identifier cannot be empty.');

        MovementIdentifierNormalizer::normalize('   ');
    }

    public function testLancaErroQuandoIdentificadorInteiroEhMenorOuIgualAZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Movement identifier must be greater than zero.');

        MovementIdentifierNormalizer::normalize(0);
    }
}
