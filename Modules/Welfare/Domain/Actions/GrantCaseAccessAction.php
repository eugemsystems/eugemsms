<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Welfare\Domain\DataObjects\GrantCaseAccessData;
use Modules\Welfare\Domain\DataObjects\RecordSafeguardingAuditEntryData;
use Modules\Welfare\Domain\Events\AccessGranted;
use Modules\Welfare\Models\CaseAccessGrant;
use Modules\Welfare\Models\SafeguardingCase;

/**
 * ACT-GrantCaseAccess (Book G BRD-08 §2/§3 ⭐⭐/BR-BRD-08-002/003/
 * AC-BRD-08-004). Lead-only in intent (enforced by whichever caller
 * checks `ViewSafeguardingCaseAction`'s lead branch before calling
 * this — this action itself does not re-derive that, matching every
 * other Action in this codebase's own "controller/caller checks
 * permission, Action does the write" split). Time-boxed by default
 * from `safeguarding.default_grant_expiry_days`; granting is itself
 * audited.
 */
final class GrantCaseAccessAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly RecordSafeguardingAuditEntryAction $recordAudit,
    ) {}

    public function execute(GrantCaseAccessData $data): CaseAccessGrant
    {
        $case = SafeguardingCase::findOrFail($data->caseId);
        $scope = new ScopeChain(schoolId: $data->schoolId);
        $expiryDays = $data->expiryDays ?? (int) $this->settings->get('safeguarding.default_grant_expiry_days', $scope);

        return $this->transaction(function () use ($data, $case, $expiryDays): CaseAccessGrant {
            $grant = CaseAccessGrant::create([
                'school_id' => $data->schoolId,
                'case_id' => $case->id,
                'user_id' => $data->userId,
                'access_level' => $data->accessLevel,
                'granted_by' => $data->grantedByUserId,
                'granted_at' => Carbon::now(),
                'reason' => $data->reason,
                'expires_at' => $expiryDays > 0 ? Carbon::now()->addDays($expiryDays) : null,
            ]);

            $this->recordAudit->execute(new RecordSafeguardingAuditEntryData(
                schoolId: $data->schoolId,
                eventType: 'grant_issued',
                userId: $data->grantedByUserId,
                userRoleAtTime: 'safeguarding_lead',
                payload: ['case_id' => $case->id, 'grant_id' => $grant->id, 'user_id' => $data->userId],
                caseId: $case->id,
                accessBasis: "grant:{$grant->ulid}",
            ));

            event(new AccessGranted($grant));

            return $grant;
        });
    }
}
