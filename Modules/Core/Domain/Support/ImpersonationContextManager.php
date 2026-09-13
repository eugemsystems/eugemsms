<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

use Modules\Core\Models\ImpersonationSession;

/**
 * Request-scoped holder for the active impersonation session, the same
 * shape as `SchoolContextManager`/`SessionContextManager`. Resolved by
 * `SetImpersonationContext` middleware (applied to every web request in
 * `bootstrap/app.php`, unconditionally — unlike school/session context
 * this needs no route parameter to resolve, so a global middleware is
 * correct here where it wasn't for those). `null` simply means "not
 * currently impersonating," the overwhelmingly common case — nothing
 * asserts it must be set the way `SchoolContextManager::assertSet()`
 * does.
 */
final class ImpersonationContextManager
{
    private ?ImpersonationSession $session = null;

    public function current(): ?ImpersonationSession
    {
        return $this->session;
    }

    public function isActive(): bool
    {
        return $this->session !== null;
    }

    public function set(ImpersonationSession $session): void
    {
        $this->session = $session;
    }

    public function clear(): void
    {
        $this->session = null;
    }
}
