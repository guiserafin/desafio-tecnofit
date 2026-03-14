<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

final class MovementRankingIntegrationTest extends IntegrationHttpTestCase
{
    public function testI01GetRankingPorIdRetornaDeadliftComTresUsuarios(): void
    {
        $response = $this->requestJson('GET', '/api/movements/1/ranking');

        self::assertSame(200, $response['status']);
        self::assertSame('Deadlift', $response['json']['movement'] ?? null);

        $expectedRanking = [
            [
                'position' => 1,
                'user' => 'Jose',
                'personal_record' => 190.0,
                'record_date' => '2021-01-06T00:00:00+00:00',
            ],
            [
                'position' => 2,
                'user' => 'Joao',
                'personal_record' => 180.0,
                'record_date' => '2021-01-02T00:00:00+00:00',
            ],
            [
                'position' => 3,
                'user' => 'Paulo',
                'personal_record' => 170.0,
                'record_date' => '2021-01-01T00:00:00+00:00',
            ],
        ];

        self::assertSame($expectedRanking, $response['json']['ranking'] ?? null);
    }

    public function testI02GetRankingPorNomeRetornaMesmoResultadoDoId(): void
    {
        $responseById = $this->requestJson('GET', '/api/movements/1/ranking');
        $responseByName = $this->requestJson('GET', '/api/movements/Deadlift/ranking');

        self::assertSame(200, $responseByName['status']);
        self::assertSame($responseById['json'], $responseByName['json']);
    }

    public function testI03BuscaCaseInsensitivePorNomeFunciona(): void
    {
        $responseByCanonicalName = $this->requestJson('GET', '/api/movements/Deadlift/ranking');
        $responseByLowercase = $this->requestJson('GET', '/api/movements/deadlift/ranking');

        self::assertSame(200, $responseByLowercase['status']);
        self::assertSame($responseByCanonicalName['json'], $responseByLowercase['json']);
    }

    public function testI04BackSquatRetornaEmpateCorretoDenseRank(): void
    {
        $response = $this->requestJson('GET', '/api/movements/2/ranking');

        self::assertSame(200, $response['status']);
        self::assertSame('Back Squat', $response['json']['movement'] ?? null);

        $expectedRanking = [
            [
                'position' => 1,
                'user' => 'Joao',
                'personal_record' => 130.0,
                'record_date' => '2021-01-03T00:00:00+00:00',
            ],
            [
                'position' => 1,
                'user' => 'Jose',
                'personal_record' => 130.0,
                'record_date' => '2021-01-03T00:00:00+00:00',
            ],
            [
                'position' => 2,
                'user' => 'Paulo',
                'personal_record' => 125.0,
                'record_date' => '2021-01-03T00:00:00+00:00',
            ],
        ];

        self::assertSame($expectedRanking, $response['json']['ranking'] ?? null);
    }

    public function testI05MovimentoInexistenteRetorna404(): void
    {
        $response = $this->requestJson('GET', '/api/movements/999/ranking');

        self::assertSame(404, $response['status']);
        self::assertSame(['error' => 'Movement not found'], $response['json']);
    }

    public function testI06MetodoInvalidoRetorna405(): void
    {
        $response = $this->requestJson('POST', '/api/movements/1/ranking');

        self::assertSame(405, $response['status']);
        self::assertSame(['error' => 'Method Not Allowed'], $response['json']);
    }

    public function testI07RespostaContemHeadersObrigatoriosJson(): void
    {
        $response = $this->requestJson('GET', '/api/movements/1/ranking');

        self::assertArrayHasKey('content-type', $response['headers']);
        self::assertStringContainsString('application/json', strtolower($response['headers']['content-type']));
        self::assertArrayHasKey('x-content-type-options', $response['headers']);
        self::assertSame('nosniff', strtolower($response['headers']['x-content-type-options']));
    }
}
