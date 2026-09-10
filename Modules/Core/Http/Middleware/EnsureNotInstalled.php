<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Support\Install\InstallProgress;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-CORE-01-001: if storage/installed.lock exists, every installer
 * route returns 404 — the installer is unreachable after install
 * (AC-CORE-01-002).
 */
final class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallProgress::isInstalled()) {
            abort(404);
        }

        return $next($request);
    }
}
