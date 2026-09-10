<?php

use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\EnsureSubscriptionActive;
use Modules\Core\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('passes through for an active tenant', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $request = Request::create('/dashboard', 'GET');
    $request->attributes->set('tenant', $tenant);

    $response = (new EnsureSubscriptionActive)->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('allows read-only access for a suspended tenant', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'suspended']);
    $request = Request::create('/dashboard', 'GET');
    $request->attributes->set('tenant', $tenant);

    $response = (new EnsureSubscriptionActive)->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok')
        ->and($request->attributes->get('subscription_read_only'))->toBeTrue();
});

it('blocks writes for a suspended tenant', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'suspended']);
    $request = Request::create('/receipts', 'POST');
    $request->attributes->set('tenant', $tenant);

    (new EnsureSubscriptionActive)->handle($request, fn () => response('ok'));
})->throws(HttpException::class);

it('refuses a cancelled tenant outright', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'cancelled']);
    $request = Request::create('/dashboard', 'GET');
    $request->attributes->set('tenant', $tenant);

    (new EnsureSubscriptionActive)->handle($request, fn () => response('ok'));
})->throws(HttpException::class);
