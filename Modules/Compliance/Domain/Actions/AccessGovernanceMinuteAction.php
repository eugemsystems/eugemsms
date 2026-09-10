<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\Exceptions\MinuteAccessDeniedException;
use Modules\Compliance\Models\GovernanceMinute;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\ActivityLogEntry;

/**
 * ACT-AccessGovernanceMinute (Book H3 CMP-04 §3 ⭐/BR-CMP-04-007). A
 * `restricted`/`confidential` minute refuses access outright when the
 * user holds none of `access_role_ids`. Every successful access is
 * logged — into `activity_log` (Book A CORE-08's own real table),
 * written directly rather than through `AuditLogger`: that interface
 * is bound to `NullAuditLogger` everywhere in this codebase (CORE-08's
 * own real implementation isn't built yet), but the table itself is
 * fully provisioned and this is the first real writer into it.
 */
final class AccessGovernanceMinuteAction extends Action
{
    /**
     * @param  array<int, int>  $userRoleIds
     */
    public function execute(int $minuteId, int $userId, array $userRoleIds): GovernanceMinute
    {
        $minute = GovernanceMinute::findOrFail($minuteId);

        if (! $minute->isAccessibleTo($userRoleIds)) {
            throw MinuteAccessDeniedException::forMinute($minute->id);
        }

        return $this->transaction(function () use ($minute, $userId): GovernanceMinute {
            ActivityLogEntry::create([
                'school_id' => $minute->school_id,
                'log_name' => 'compliance',
                'description' => "Accessed governance minute #{$minute->id} ({$minute->confidentiality}).",
                'subject_type' => GovernanceMinute::class,
                'subject_id' => $minute->id,
                'causer_type' => User::class,
                'causer_id' => $userId,
                'event' => 'governance_minute.accessed',
                'created_at' => Carbon::now(),
            ]);

            return $minute;
        });
    }
}
