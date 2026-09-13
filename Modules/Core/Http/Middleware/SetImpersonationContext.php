<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Support\ImpersonationContext;
use Modules\Core\Models\ImpersonationSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Book A CORE-05 BR-CORE-05-018. Applied to every web request in
 * `bootstrap/app.php` (not a per-route-group alias like
 * `SetSchoolContext` — impersonation state depends on nothing route-
 * specific, so there's no reason to make every module remember to ask
 * for it). Reads the same `session('impersonation_session_id')`
 * `Impersonate::start()`/`ImpersonationBanner` already manage — this
 * middleware is purely a reader, never a writer, of that session key.
 *
 * Fills the gap `Impersonate`'s own docblock flagged: "no middleware
 * sets [the active impersonation session], no context singleton holds
 * it" — `ImpersonationGuard::assertPermitted()` needs exactly this to
 * be callable from inside a domain Action via `Action::assertNotImpersonating()`.
 */
final class SetImpersonationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionId = $request->session()->get('impersonation_session_id');

        if ($sessionId !== null) {
            $session = ImpersonationSession::query()->find((int) $sessionId);

            if ($session !== null && $session->isActive()) {
                ImpersonationContext::set($session);
            }
        }

        return $next($request);
    }
}
