<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\SerpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Book A Part 1.6: every platform exception carries its own
        // stable error code and HTTP status — this is the one place
        // that turns `toErrorEnvelope()` into an actual JSON response,
        // for every module rather than each wiring its own renderer.
        $exceptions->render(fn (SerpException $e) => response()->json($e->toErrorEnvelope(), $e->httpStatus()));
    })->create();
