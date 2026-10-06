<?php

use Illuminate\Support\Facades\Route;

it('serves a public OpenAPI document without authentication (AC-INT-04-005)', function (): void {
    $response = $this->getJson('/api/v1/openapi.json')->assertOk();

    expect($response->json('openapi'))->toStartWith('3.')
        ->and($response->json('info.version'))->toBe('v1')
        ->and($response->json('paths'))->toHaveKey('/openapi.json')
        ->and($response->json('paths./openapi.json.get.security'))->toBeNull();
});

it('documents exactly the api/v1 routes the router holds, so it cannot drift (BR-INT-04-010)', function (): void {
    $document = $this->getJson('/api/v1/openapi.json')->json();

    $documented = collect($document['paths'])
        ->flatMap(fn (array $methods, string $path) => collect(array_keys($methods))->map(fn (string $m) => strtoupper($m).' '.$path))
        ->sort()->values()->all();

    $actual = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn ($route) => collect($route->methods())->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS'], true))
            ->map(fn ($m) => $m.' /'.substr($route->uri(), 7)))
        ->map(fn (string $s) => preg_replace('/\{(\w+)\?\}/', '{$1}', $s))
        ->sort()->values()->all();

    expect($documented)->toBe($actual);
});

it('marks token-protected routes with bearer security and their required ability', function (): void {
    $document = $this->getJson('/api/v1/openapi.json')->json();

    $operation = $document['paths']['/finance/balances']['get'];

    expect($operation['security'])->toBe([['bearerAuth' => []]])
        ->and($operation['x-required-abilities'])->toBe(['fees.read'])
        ->and($document['paths']['/finance/invoices/{invoice}']['get']['parameters'][0]['name'])->toBe('invoice');
});
