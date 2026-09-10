<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Modules\Core\Http\Middleware\EnsureNotInstalled;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

afterEach(function (): void {
    File::delete(storage_path('installed.lock'));
});

it('passes through when the platform is not yet installed', function (): void {
    $response = (new EnsureNotInstalled)->handle(Request::create('/install'), fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('404s every installer route once installed.lock exists (BR-CORE-01-001)', function (): void {
    File::put(storage_path('installed.lock'), '{}');

    (new EnsureNotInstalled)->handle(Request::create('/install'), fn () => response('ok'));
})->throws(NotFoundHttpException::class);
