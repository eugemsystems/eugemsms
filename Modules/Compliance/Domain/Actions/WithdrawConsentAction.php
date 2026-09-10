<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\DataObjects\WithdrawConsentData;
use Modules\Compliance\Domain\Events\ConsentWithdrawn;
use Modules\Compliance\Domain\Exceptions\ConsentNotWithdrawableException;
use Modules\Compliance\Models\Consent;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-WithdrawConsent (Book H3 CMP-03 §3 ⭐/BR-CMP-03-003/004
 * (AC-CMP-03-001)). Refuses when the consent's own type is not
 * withdrawable (`legal_obligation`/`vital_interest` bases, per
 * BR-CMP-03-004). Sets withdrawal state on the SAME row — never a new
 * row, never a deletion — and fires `ConsentWithdrawn` so any module
 * that needs to react immediately (BR-CMP-03-003's "same day") can.
 */
final class WithdrawConsentAction extends Action
{
    public function execute(WithdrawConsentData $data): Consent
    {
        $consent = Consent::with('consentType')->findOrFail($data->consentId);

        if (! $consent->consentType->is_withdrawable) {
            throw ConsentNotWithdrawableException::forConsent($consent->id, $consent->consentType->lawful_basis);
        }

        return $this->transaction(function () use ($consent, $data): Consent {
            $consent->update([
                'withdrawn_at' => Carbon::now(),
                'withdrawn_by' => $data->withdrawnByUserId,
                'withdrawal_reason' => $data->withdrawalReason,
            ]);

            event(new ConsentWithdrawn($consent));

            return $consent;
        });
    }
}
