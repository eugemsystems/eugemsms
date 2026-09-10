<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\Events\ConsentWithdrawn;
use Modules\Welfare\Models\MedicalConsent;

/**
 * ACT-WithdrawMedicalConsent (Book G BRD-06 §7 — `ConsentWithdrawn`).
 */
final class WithdrawMedicalConsentAction extends Action
{
    public function execute(int $consentId, string $reason): MedicalConsent
    {
        $consent = MedicalConsent::findOrFail($consentId);

        return $this->transaction(function () use ($consent, $reason): MedicalConsent {
            $consent->update([
                'withdrawn_at' => Carbon::now(),
                'withdrawn_reason' => $reason,
            ]);

            event(new ConsentWithdrawn($consent));

            return $consent;
        });
    }
}
