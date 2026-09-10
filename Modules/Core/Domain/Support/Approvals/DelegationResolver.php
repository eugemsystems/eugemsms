<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Approvals;

use Illuminate\Support\Carbon;
use Modules\Core\Models\ApprovalDelegation;

/**
 * Book A CORE-07 BR-CORE-07-009. Delegation transfers approval
 * capability for a bounded window — this finds a currently-active
 * delegation that makes `$actorId` stand in for one of the request's
 * legitimate resolved approvers.
 */
final class DelegationResolver
{
    /**
     * @param  array<int, int>  $resolvedApprovers
     */
    public function delegatorFor(int $actorId, int $schoolId, string $approvableType, array $resolvedApprovers): ?int
    {
        if ($resolvedApprovers === []) {
            return null;
        }

        $now = Carbon::now();

        $delegation = ApprovalDelegation::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('delegate_id', $actorId)
            ->where('is_active', true)
            ->whereIn('delegator_id', $resolvedApprovers)
            ->where(fn ($query) => $query->whereNull('approvable_type')->orWhere('approvable_type', $approvableType))
            ->get()
            ->first(fn (ApprovalDelegation $delegation): bool => $delegation->isActiveAt($now));

        return $delegation?->delegator_id;
    }
}
