<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Core\Domain\Exceptions\SubscriptionPastDueException;
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

it('passes through for a trial tenant', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'trial']);
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

it('allows reads for a cancelled tenant — BR-SAA-01-008', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'cancelled']);
    $request = Request::create('/dashboard', 'GET');
    $request->attributes->set('tenant', $tenant);

    $response = (new EnsureSubscriptionActive)->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('blocks non-export writes for a cancelled tenant', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'cancelled']);
    $request = Request::create('/receipts', 'POST');
    $request->attributes->set('tenant', $tenant);

    (new EnsureSubscriptionActive)->handle($request, fn () => response('ok'));
})->throws(HttpException::class);

it('allows an export-named route to run for a cancelled tenant — AC-SAA-01-005', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'cancelled']);
    Route::post('/export/school-data', fn () => response('ok'))->name('export.school_data');
    $request = Request::create('/export/school-data', 'POST');
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
    $request->attributes->set('tenant', $tenant);

    $response = (new EnsureSubscriptionActive)->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('throws SubscriptionPastDueException with a payment link when a past_due tenant writes — AC-SAA-01-001', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'past_due']);
    $request = Request::create('/receipts', 'POST');
    $request->attributes->set('tenant', $tenant);

    try {
        (new EnsureSubscriptionActive)->handle($request, fn () => response('ok'));
        $this->fail('Expected SubscriptionPastDueException.');
    } catch (SubscriptionPastDueException $exception) {
        expect($exception->details())->toHaveKey('payment_portal_url');
    }
});

it('adds a subscription notice header for a grace-period read — AC-SAA-01-001', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'grace']);
    $request = Request::create('/dashboard', 'GET');
    $request->attributes->set('tenant', $tenant);

    $response = (new EnsureSubscriptionActive)->handle($request, fn () => response('ok'));

    expect($response->headers->get('X-Subscription-Notice'))->not->toBeNull();
});

it('never gates a safeguarding-critical write, even when suspended — AC-SAA-01-002', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'suspended']);
    Route::post('/safeguarding/concerns', fn () => response('ok'))->name('safeguarding.concerns.store');
    $request = Request::create('/safeguarding/concerns', 'POST');
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
    $request->attributes->set('tenant', $tenant);

    $response = (new EnsureSubscriptionActive)->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('never gates a safeguarding-critical write, even when cancelled — AC-SAA-01-002', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'cancelled']);
    Route::post('/safeguarding/cases', fn () => response('ok'))->name('safeguarding.cases.store');
    $request = Request::create('/safeguarding/cases', 'POST');
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
    $request->attributes->set('tenant', $tenant);

    $response = (new EnsureSubscriptionActive)->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});
