<?php

use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\ModuleNotEnabledException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Http\Middleware\EnsureModuleEnabled;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolModule;

it('allows CORE by default even without an explicit entitlement row', function (): void {
    SchoolContext::set(School::factory()->create());

    $response = (new EnsureModuleEnabled)->handle(Request::create('/'), fn () => response('ok'), 'CORE');

    expect($response->getContent())->toBe('ok');
});

it('blocks a module with no entitlement row', function (): void {
    SchoolContext::set(School::factory()->create());

    (new EnsureModuleEnabled)->handle(Request::create('/'), fn () => response('ok'), 'BRD');
})->throws(ModuleNotEnabledException::class);

it('allows an explicitly enabled module', function (): void {
    $school = School::factory()->create();
    SchoolModule::factory()->for($school)->create(['module_code' => 'FIN', 'is_enabled' => true]);
    SchoolContext::set($school);

    $response = (new EnsureModuleEnabled)->handle(Request::create('/'), fn () => response('ok'), 'FIN');

    expect($response->getContent())->toBe('ok');
});

it('blocks a module explicitly disabled for the school', function (): void {
    $school = School::factory()->create();
    SchoolModule::factory()->for($school)->create(['module_code' => 'FIN', 'is_enabled' => false]);
    SchoolContext::set($school);

    (new EnsureModuleEnabled)->handle(Request::create('/'), fn () => response('ok'), 'FIN');
})->throws(ModuleNotEnabledException::class);

it('blocks a module whose entitlement has expired', function (): void {
    $school = School::factory()->create();
    SchoolModule::factory()->for($school)->create([
        'module_code' => 'FIN',
        'is_enabled' => true,
        'expires_at' => now()->subDay(),
    ]);
    SchoolContext::set($school);

    (new EnsureModuleEnabled)->handle(Request::create('/'), fn () => response('ok'), 'FIN');
})->throws(ModuleNotEnabledException::class);
