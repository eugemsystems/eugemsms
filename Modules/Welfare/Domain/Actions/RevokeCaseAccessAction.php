<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\RecordSafeguardingAuditEntryData;
use Modules\Welfare\Domain\Events\AccessRevoked;
use Modules\Welfare\Models\CaseAccessGrant;

/**
 * ACT-RevokeCaseAccess (Book G BRD-08 §2/BR-BRD-08-003).
 */
final class RevokeCaseAccessAction extends Action
{
    public function __construct(
        private readonly RecordSafeguardingAuditEntryAction $recordAudit,
    ) {}

    public function execute(int $grantId, int $revokedByUserId, string $reason): CaseAccessGrant
    {
        $grant = CaseAccessGrant::findOrFail($grantId);

        return $this->transaction(function () use ($grant, $revokedByUserId, $reason): CaseAccessGrant {
            $grant->update([
                'revoked_at' => Carbon::now(),
                'revoked_by' => $revokedByUserId,
                'revocation_reason' => $reason,
            ]);

            $this->recordAudit->execute(new RecordSafeguardingAuditEntryData(
                schoolId: $grant->school_id,
                eventType: 'grant_revoked',
                userId: $revokedByUserId,
                userRoleAtTime: 'safeguarding_lead',
                payload: ['case_id' => $grant->case_id, 'grant_id' => $grant->id],
                caseId: $grant->case_id,
                accessBasis: "grant:{$grant->ulid}",
            ));

            event(new AccessRevoked($grant));

            return $grant;
        });
    }
}
