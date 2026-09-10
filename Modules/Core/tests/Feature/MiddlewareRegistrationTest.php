<?php

use Modules\Core\Http\Middleware\EnforceTokenAbility;
use Modules\Core\Http\Middleware\EnsureModuleEnabled;
use Modules\Core\Http\Middleware\EnsureSubscriptionActive;
use Modules\Core\Http\Middleware\RecordActivity;
use Modules\Core\Http\Middleware\ResolveTenant;
use Modules\Core\Http\Middleware\SetSchoolContext;
use Modules\Core\Http\Middleware\SetSessionContext;

it('registers every middleware alias from the Book A Part 1.10 stack', function (): void {
    $router = app('router');

    foreach ([
        'serp.resolve-tenant' => ResolveTenant::class,
        'serp.subscription-active' => EnsureSubscriptionActive::class,
        'serp.school-context' => SetSchoolContext::class,
        'serp.session-context' => SetSessionContext::class,
        'serp.module-enabled' => EnsureModuleEnabled::class,
        'serp.token-ability' => EnforceTokenAbility::class,
        'serp.record-activity' => RecordActivity::class,
    ] as $alias => $class) {
        expect($router->getMiddleware())->toHaveKey($alias, $class);
    }
});

it('registers the serp.web and serp.api middleware groups in the documented order', function (): void {
    $router = app('router');
    $groups = $router->getMiddlewareGroups();

    expect($groups)->toHaveKeys(['serp.web', 'serp.api']);

    expect($groups['serp.web'])->toBe([
        'serp.resolve-tenant',
        'serp.subscription-active',
        'auth',
        'serp.school-context',
        'serp.session-context',
        'serp.record-activity',
    ]);

    expect($groups['serp.api'])->toBe([
        'serp.resolve-tenant',
        'serp.subscription-active',
        'auth:sanctum',
        'serp.school-context',
        'serp.session-context',
        'serp.token-ability',
        'serp.record-activity',
    ]);
});
