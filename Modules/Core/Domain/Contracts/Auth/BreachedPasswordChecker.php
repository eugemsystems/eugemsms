<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Auth;

/**
 * Book A CORE-05 BR-CORE-05-004. A real implementation checks a password
 * against a breached-password corpus (e.g. a k-anonymity HIBP lookup);
 * no such integration module exists yet, so `NullBreachedPasswordChecker`
 * is bound by default — same deferred-dependency shape as
 * `NullTermBalanceProvider` (CORE-03), `NullTenantTierProvider` (CORE-04).
 */
interface BreachedPasswordChecker
{
    public function isBreached(string $password): bool;
}
