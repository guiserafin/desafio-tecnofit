<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Infrastructure\Http;

use InvalidArgumentException;
use RuntimeException;

final class Response
{
    private int $statusCode;

    /**
     * @var array<string, mixed>
     */
    private array $payload;

    /**
     * @var array<string, string>
     */
    private array $headers;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    private function __construct(int $statusCode, array $payload, array $headers = [])
    {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new InvalidArgumentException('Invalid HTTP status code.');
        }

        $this->statusCode = $statusCode;
        $this->payload = $payload;
        $this->headers = $headers;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public static function json(array $payload, int $statusCode = 200, array $headers = []): self
    {
        return new self($statusCode, $payload, $headers);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        $headers = array_merge(
            [
                'Content-Type' => 'application/json',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $this->headers
        );

        foreach ($headers as $header => $value) {
            header(sprintf('%s: %s', $header, $value));
        }

        echo $this->encodePayload();
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    private function encodePayload(): string
    {
        $json = json_encode(
            $this->payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );

        if ($json === false) {
            throw new RuntimeException(sprintf('Unable to encode response payload: %s', json_last_error_msg()));
        }

        return $json;
    }
}
