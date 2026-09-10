<?php

use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Http\Middleware\EnforceTokenAbility;

it('passes through when the route declares no abilities', function (): void {
    $request = Request::create('/');

    $response = (new EnforceTokenAbility)->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('passes through when the user model has no tokenCan method yet', function (): void {
    $request = Request::create('/');
    $request->setUserResolver(fn () => new stdClass);

    $response = (new EnforceTokenAbility)->handle($request, fn () => response('ok'), 'fees.read');

    expect($response->getContent())->toBe('ok');
});

it('allows the request when the token carries the required ability', function (): void {
    $user = new class
    {
        public function tokenCan(string $ability): bool
        {
            return $ability === 'fees.read';
        }
    };

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $response = (new EnforceTokenAbility)->handle($request, fn () => response('ok'), 'fees.read');

    expect($response->getContent())->toBe('ok');
});

it('refuses the request when the token lacks a required ability', function (): void {
    $user = new class
    {
        public function tokenCan(string $ability): bool
        {
            return false;
        }
    };

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    (new EnforceTokenAbility)->handle($request, fn () => response('ok'), 'fees.write');
})->throws(InsufficientScopeException::class);
