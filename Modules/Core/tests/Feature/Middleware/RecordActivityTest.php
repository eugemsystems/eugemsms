<?php

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\RecordActivity;

it('stamps last_seen_at on the authenticated user', function (): void {
    $user = User::factory()->create(['last_seen_at' => null]);
    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    (new RecordActivity)->handle($request, fn () => response('ok'));

    expect($user->refresh()->last_seen_at)->not->toBeNull();
});

it('propagates and echoes back a request id', function (): void {
    $request = Request::create('/');
    $request->headers->set('X-Request-Id', 'req-123');

    $response = (new RecordActivity)->handle($request, fn () => response('ok'));

    expect($response->headers->get('X-Request-Id'))->toBe('req-123');
});

it('generates a request id when none is supplied', function (): void {
    $response = (new RecordActivity)->handle(Request::create('/'), fn () => response('ok'));

    expect($response->headers->get('X-Request-Id'))->not->toBeEmpty();
});
