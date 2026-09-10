<?php

use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\ResolveTenant;
use Modules\Core\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function (): void {
    config(['app.url' => 'http://serp.test']);
});

it('resolves the tenant from the request subdomain', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'sunrise']);

    $request = Request::create('http://sunrise.serp.test/dashboard');
    $middleware = new ResolveTenant;

    $response = $middleware->handle($request, function (Request $request) use ($tenant) {
        expect($request->attributes->get('tenant')->id)->toBe($tenant->id);

        return response('ok');
    });

    expect($response->getContent())->toBe('ok');
});

it('404s when the subdomain does not match a known tenant', function (): void {
    $request = Request::create('http://unknown.serp.test/dashboard');
    $middleware = new ResolveTenant;

    $middleware->handle($request, fn (Request $request) => response('ok'));
})->throws(NotFoundHttpException::class);

it('404s when there is no subdomain and no authenticated user', function (): void {
    $request = Request::create('http://serp.test/dashboard');
    $middleware = new ResolveTenant;

    $middleware->handle($request, fn (Request $request) => response('ok'));
})->throws(NotFoundHttpException::class);
