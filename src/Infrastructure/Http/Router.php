<?php

declare(strict_types=1);

namespace Tecnofit\MovementRanking\Infrastructure\Http;

use InvalidArgumentException;
use RuntimeException;

final class Router
{
    /**
     * @var list<array{
     *     method: string,
     *     regex: string,
     *     parameter_names: list<string>,
     *     handler: callable
     * }>
     */
    private array $routes = [];

    public function add(string $method, string $pathTemplate, callable $handler): void
    {
        $normalizedMethod = strtoupper(trim($method));
        if ($normalizedMethod === '') {
            throw new InvalidArgumentException('Route method cannot be empty.');
        }

        [$regex, $parameterNames] = $this->compilePathTemplate($pathTemplate);

        $this->routes[] = [
            'method' => $normalizedMethod,
            'regex' => $regex,
            'parameter_names' => $parameterNames,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $pathMatchedWithDifferentMethod = false;

        foreach ($this->routes as $route) {
            $matches = [];
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $request->method) {
                $pathMatchedWithDifferentMethod = true;

                continue;
            }

            $parameters = [];
            foreach ($route['parameter_names'] as $parameterName) {
                $parameterValue = $matches[$parameterName] ?? null;
                if (!is_string($parameterValue)) {
                    throw new RuntimeException(
                        sprintf('Route parameter "%s" could not be resolved.', $parameterName)
                    );
                }

                $parameters[] = rawurldecode($parameterValue);
            }

            $response = ($route['handler'])(...$parameters);
            if (!$response instanceof Response) {
                throw new RuntimeException('Route handler must return an instance of Response.');
            }

            return $response;
        }

        if ($pathMatchedWithDifferentMethod) {
            return Response::json(['error' => 'Method Not Allowed'], 405);
        }

        return Response::json(['error' => 'Not Found'], 404);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function compilePathTemplate(string $pathTemplate): array
    {
        if ($pathTemplate === '') {
            throw new InvalidArgumentException('Route path template cannot be empty.');
        }

        $normalizedTemplate = '/' . ltrim($pathTemplate, '/');
        if ($normalizedTemplate !== '/') {
            $normalizedTemplate = rtrim($normalizedTemplate, '/');
        }

        if ($normalizedTemplate === '/') {
            return ['#^/$#', []];
        }

        $segments = explode('/', trim($normalizedTemplate, '/'));
        $patternSegments = [];
        $parameterNames = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $parameterMatch) === 1) {
                $parameterName = $parameterMatch[1];
                if (in_array($parameterName, $parameterNames, true)) {
                    throw new InvalidArgumentException(
                        sprintf('Route path template contains duplicated parameter "%s".', $parameterName)
                    );
                }

                $parameterNames[] = $parameterName;
                $patternSegments[] = sprintf('(?P<%s>[^/]+)', $parameterName);

                continue;
            }

            $patternSegments[] = preg_quote($segment, '#');
        }

        $regex = '#^/' . implode('/', $patternSegments) . '$#';

        return [$regex, $parameterNames];
    }
}
