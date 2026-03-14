<?php

declare(strict_types=1);

use Tecnofit\MovementRanking\Application\UseCase\GetMovementRankingUseCase;
use Tecnofit\MovementRanking\Infrastructure\Configuration\Environment;
use Tecnofit\MovementRanking\Infrastructure\Database\Connection;
use Tecnofit\MovementRanking\Infrastructure\Database\Repository\MySQLMovementRepository;
use Tecnofit\MovementRanking\Infrastructure\Http\ErrorMapper;
use Tecnofit\MovementRanking\Infrastructure\Http\Request;
use Tecnofit\MovementRanking\Infrastructure\Http\Response;
use Tecnofit\MovementRanking\Infrastructure\Http\Router;
use Tecnofit\MovementRanking\Infrastructure\Logging\Logger;
use Tecnofit\MovementRanking\Presentation\Controller\MovementRankingController;

require dirname(__DIR__) . '/vendor/autoload.php';

$requestStartedAt = microtime(true);
$request = Request::fromGlobals();
$logger = new Logger();
$isProduction = strtolower(Environment::get('APP_ENV', 'production')) === 'production';
$isDebug = Environment::getBoolean('APP_DEBUG', false);

$createErrorResponse = static function (Throwable $throwable) use ($isProduction, $isDebug, $logger): Response {
    $statusCode = ErrorMapper::toStatusCode($throwable);
    $logger->logServerError($throwable, $statusCode);

    return Response::json(ErrorMapper::toPayload($throwable, $isProduction, $isDebug), $statusCode);
};

$sendResponse = static function (Response $response) use ($logger, $request, $requestStartedAt): void {
    $response->send();

    $durationMs = (microtime(true) - $requestStartedAt) * 1000;
    $logger->logRequest($request->method, $request->path, $response->getStatusCode(), $durationMs);
};

set_error_handler(
    static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }
);

set_exception_handler(
    static function (Throwable $throwable) use ($createErrorResponse, $sendResponse): void {
        static $isHandlingException = false;

        if ($isHandlingException) {
            http_response_code(500);
            header('Content-Type: application/json');
            header('X-Content-Type-Options: nosniff');
            echo '{"error":"Internal Server Error"}';

            return;
        }

        $isHandlingException = true;
        $sendResponse($createErrorResponse($throwable));
    }
);

register_shutdown_function(
    static function () use ($createErrorResponse, $sendResponse): void {
        $lastError = error_get_last();
        if ($lastError === null) {
            return;
        }

        $type = $lastError['type'] ?? null;
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!is_int($type) || !in_array($type, $fatalTypes, true)) {
            return;
        }

        $message = is_string($lastError['message'] ?? null) ? $lastError['message'] : 'Fatal error';
        $file = is_string($lastError['file'] ?? null) ? $lastError['file'] : __FILE__;
        $line = is_int($lastError['line'] ?? null) ? $lastError['line'] : 0;

        $fatalError = new ErrorException($message, 0, $type, $file, $line);
        $sendResponse($createErrorResponse($fatalError));
    }
);

$router = new Router();
$router->add(
    method: 'GET',
    pathTemplate: '/api/movements/{identifier}/ranking',
    handler: static function (string $identifier): Response {
        $connection = Connection::createFromEnvironment();
        $movementRepository = new MySQLMovementRepository($connection);
        $useCase = new GetMovementRankingUseCase($movementRepository);
        $movementRankingController = new MovementRankingController($useCase);

        return $movementRankingController->getRanking($identifier);
    }
);

$response = $router->dispatch($request);
$sendResponse($response);
