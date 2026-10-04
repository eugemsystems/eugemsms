<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Safeguarding\Concerns;

use Illuminate\Support\Facades\Auth;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Domain\Support\ImpersonationContext;

/**
 * Book G BRD-08 §2/BR-BRD-08-001 ⭐ — the hard, first-check exclusion
 * every screen touching a safeguarding concern, case, entry,
 * counselling session, or the hardened audit stream must apply,
 * mirroring `ViewSafeguardingCaseAction`'s own first check almost
 * verbatim.
 *
 * A vendor-type user cannot hold any `safeguarding.*` permission at
 * all (`Role::givePermissionTo()`'s own hard refusal), so
 * `authorizePermission()` alone already stops a plain vendor login —
 * but it does NOT stop a support engineer who is impersonating a
 * school staff member who DOES legitimately hold one. During
 * impersonation, `Auth::user()` resolves to the IMPERSONATED user, so
 * every ordinary permission check in this codebase sees that user's
 * own permissions, not the vendor's. This trait's check is what closes
 * that gap — it must run before any of this screen's own queries, not
 * only before a mutating action.
 */
trait BlocksVendorAndImpersonation
{
    protected function abortIfVendorOrImpersonating(): void
    {
        $user = Auth::user();

        abort_if($user === null, 403);
        abort_if($user->user_type === UserType::Vendor, 403);
        abort_if(ImpersonationContext::isActive(), 403);
    }
}
