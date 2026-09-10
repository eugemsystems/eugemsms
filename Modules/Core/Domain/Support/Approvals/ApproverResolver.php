<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Approvals;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Contracts\Approvals\Approvable;
use Modules\Core\Domain\Registry\DynamicApproverResolverRegistry;
use Modules\Core\Models\ApprovalStep;

/**
 * Book A CORE-07 BR-CORE-07-002/003. `user` → that person; `role` →
 * every holder of that role in this school; `dynamic` → a registered
 * resolver run against the approvable. An empty result is a legitimate
 * outcome — the caller (RequestApprovalAction/ApproveStepAction) is
 * responsible for BR-CORE-07-003's "blocks, never auto-approves".
 */
final class ApproverResolver
{
    /**
     * @return array<int, int> user ids, never containing duplicates
     */
    public function resolve(ApprovalStep $step, int $schoolId, Approvable $approvable): array
    {
        $ids = match ($step->approver_type) {
            'user' => $step->approver_user_id !== null ? [$step->approver_user_id] : [],
            'role' => $this->usersWithRole($step->approver_role_id, $schoolId),
            'dynamic' => $step->dynamic_resolver !== null
                ? DynamicApproverResolverRegistry::resolve($step->dynamic_resolver, $approvable)
                : [],
            default => [],
        };

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, int>
     */
    private function usersWithRole(?int $roleId, int $schoolId): array
    {
        if ($roleId === null) {
            return [];
        }

        return DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('school_id', $schoolId)
            ->where('model_type', (new User)->getMorphClass())
            ->pluck('model_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
