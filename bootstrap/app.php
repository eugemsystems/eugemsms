<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Exceptions\SerpException;
use Modules\Core\Http\Middleware\EnsureTwoFactorIsEnrolled;
use Modules\Core\Http\Middleware\SetImpersonationContext;
use Modules\Core\Http\Support\ApiResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // `app` (php-fpm) is never reachable except from `web` (nginx) over the Docker-internal
        // network -- port 9000 isn't published to the host in either compose file -- so trusting
        // every proxy is safe here. Without this, url()/asset()/secure requests all resolve to
        // http even when a TLS-terminating proxy in front of `web` (Traefik locally, a load
        // balancer in production) forwards X-Forwarded-Proto: https, which Laravel ignores by
        // default and browsers then block as mixed content (e.g. Livewire's own <script src>).
        $middleware->trustProxies(at: '*');

        // BR-CORE-05-005: applied to every web request (not just the
        // module route groups that remember to ask for it) so a
        // mandatory-2FA-but-unenrolled user can't reach anything by
        // going around a specific module's own middleware list — the
        // middleware itself is what decides who it actually applies to.
        $middleware->web(append: [EnsureTwoFactorIsEnrolled::class, SetImpersonationContext::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Book A Part 1.6: every platform exception carries its own
        // stable error code and HTTP status — this is the one place
        // that turns `toErrorEnvelope()` into an actual JSON response,
        // for every module rather than each wiring its own renderer.
        $exceptions->render(fn (SerpException $e, Request $request) => response()->json(
            $request->is('api/*') ? $e->toErrorEnvelope() + ['meta' => ApiResponse::meta()] : $e->toErrorEnvelope(),
            $e->httpStatus(),
        ));

        // Volume 1 §9.2: the same envelope for the framework's own API failures.
        $exceptions->render(fn (ValidationException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::error('VALIDATION_FAILED', $e->getMessage(), 422, ['errors' => $e->errors()])
            : null);
        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::error('UNAUTHENTICATED', 'Sign in to continue.', 401)
            : null);
        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::error('RATE_LIMITED', 'Too many requests. Slow down and retry shortly.', 429, ['retry_after' => $e->getHeaders()['Retry-After'] ?? null])
            : null);
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::error('NOT_FOUND', 'That resource does not exist.', 404)
            : null);
    })->create();
