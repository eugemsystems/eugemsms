<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Support;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Book J INT-04 §5/BR-INT-04-010. The OpenAPI 3.0 document is derived at
 * request time from the router's own `api/v1` routes (path, verbs, path
 * parameters, the ability middleware that guards each) and from the
 * `FormRequest` a controller method type-hints (its `rules()` become the
 * request body schema), so it cannot drift from what the application runs.
 * Nothing here is a hand-maintained path list. Inline `$request->validate()`
 * calls cannot be reflected, so those routes document no request body.
 */
final class OpenApiDocumentBuilder
{
    private const string PREFIX = 'api/v1/';

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $paths = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), self::PREFIX)) {
                continue;
            }

            $path = '/'.substr($route->uri(), strlen(self::PREFIX));
            $path = (string) preg_replace('/\{(\w+)\?\}/', '{$1}', $path);

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $paths[$path][strtolower($method)] = $this->operation($route, $method);
            }
        }

        ksort($paths);

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('app.name', 'SERP').' public API',
                'version' => 'v1',
                'description' => 'Generated from the routes and validation rules the application runs.',
            ],
            'servers' => [['url' => rtrim((string) config('app.url'), '/').'/api/v1']],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(Route $route, string $method): array
    {
        $middleware = $route->gatherMiddleware();
        $abilities = $this->abilities($middleware);
        $isPublic = $this->isPublic($middleware);

        $operation = [
            'operationId' => $route->getName() ?? Str::camel($method.'_'.str_replace(['/', '{', '}', '-', '.'], '_', $route->uri())),
            'summary' => $this->summary($route),
            'tags' => [explode('/', substr($route->uri(), strlen(self::PREFIX)))[0]],
            'responses' => [
                '200' => ['description' => 'Success envelope: {success, data, meta}.'],
                '422' => ['description' => 'Validation failed.'],
            ],
        ];

        if (! $isPublic) {
            $operation['security'] = [['bearerAuth' => []]];
            $operation['responses']['401'] = ['description' => 'Missing or invalid credential.'];
            $operation['responses']['403'] = ['description' => 'Credential lacks the required ability.'];
        }

        if ($this->isRateLimited($middleware)) {
            $operation['responses']['429'] = [
                'description' => 'Per-client rate limit exceeded.',
                'headers' => ['Retry-After' => ['schema' => ['type' => 'integer'], 'description' => 'Seconds until the client may retry.']],
            ];
        }

        if ($abilities !== []) {
            $operation['x-required-abilities'] = $abilities;
        }

        $parameters = array_map(
            fn (string $name): array => ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
            $route->parameterNames(),
        );

        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        $rules = $this->formRequestRules($route);

        if ($rules !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => ['application/json' => ['schema' => $this->schemaFromRules($rules)]],
            ];
        }

        return $operation;
    }

    private function summary(Route $route): string
    {
        $action = $route->getActionName();

        if (str_contains($action, '@')) {
            [$class, $methodName] = explode('@', $action);

            return Str::headline(class_basename($class)).' · '.Str::headline($methodName);
        }

        return Str::headline($route->getName() ?? $route->uri());
    }

    /**
     * @param  array<int, string>  $middleware
     * @return array<int, string>
     */
    private function abilities(array $middleware): array
    {
        $abilities = [];

        foreach ($middleware as $entry) {
            if (! is_string($entry) || ! str_contains($entry, ':')) {
                continue;
            }

            [$name, $arguments] = explode(':', $entry, 2);

            if (in_array($name, ['serp.token-ability', 'serp.api-client'], true)) {
                array_push($abilities, ...explode(',', $arguments));
            }
        }

        return array_values(array_unique($abilities));
    }

    /**
     * @param  array<int, string>  $middleware
     */
    private function isPublic(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if (is_string($entry) && (str_starts_with($entry, 'auth') || str_starts_with($entry, 'serp.api'))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $middleware
     */
    private function isRateLimited(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if (is_string($entry) && (str_starts_with($entry, 'throttle') || str_starts_with($entry, 'serp.api-client'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formRequestRules(Route $route): ?array
    {
        $action = $route->getAction('uses');

        try {
            $reflection = match (true) {
                is_string($action) && str_contains($action, '@') => new ReflectionMethod(...explode('@', $action)),
                $action instanceof Closure => new ReflectionFunction($action),
                default => null,
            };
        } catch (\ReflectionException) {
            return null;
        }

        foreach ($reflection?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), FormRequest::class)) {
                /** @var array<string, mixed> $rules */
                $rules = (new ($type->getName()))->rules();

                return $rules;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function schemaFromRules(array $rules): array
    {
        $properties = [];
        $required = [];

        foreach ($rules as $field => $rule) {
            $parts = is_array($rule) ? array_map('strval', array_filter($rule, 'is_string')) : explode('|', (string) $rule);
            $type = match (true) {
                in_array('integer', $parts, true) => 'integer',
                in_array('numeric', $parts, true) => 'number',
                in_array('boolean', $parts, true) => 'boolean',
                in_array('array', $parts, true) => 'array',
                default => 'string',
            };
            $property = ['type' => $type];

            if (in_array('date', $parts, true) || in_array('date_format:c', $parts, true)) {
                $property['format'] = 'date-time';
            }

            foreach ($parts as $part) {
                if (str_starts_with($part, 'in:')) {
                    $property['enum'] = explode(',', substr($part, 3));
                }

                if (str_starts_with($part, 'max:') && $type === 'string') {
                    $property['maxLength'] = (int) substr($part, 4);
                }
            }

            $properties[$field] = $property;

            if (in_array('required', $parts, true)) {
                $required[] = $field;
            }
        }

        return ['type' => 'object', 'properties' => $properties] + ($required !== [] ? ['required' => $required] : []);
    }
}
