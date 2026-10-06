<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\EndSponsorshipBeneficiaryData;
use Modules\People\Models\FeeLiability;
use Modules\People\Models\SponsorshipBeneficiary;

/**
 * ACT-EndSponsorshipBeneficiary (Book C PPL-03 §3/BR-PPL-03-020). Ends support
 * for one learner — a human decision, never automatic — and closes the sponsor's
 * liability from that date. Invoices already issued are not touched
 * (BR-PPL-03-010).
 */
final class EndSponsorshipBeneficiaryAction extends Action
{
    public function execute(EndSponsorshipBeneficiaryData $data): SponsorshipBeneficiary
    {
        $beneficiary = SponsorshipBeneficiary::findOrFail($data->beneficiaryId);

        if ($beneficiary->status !== 'active') {
            throw new InvalidArgumentException('Only an active beneficiary can be ended.');
        }

        if (! in_array($data->newStatus, ['ended', 'withdrawn'], true)) {
            throw new InvalidArgumentException("Unknown status [{$data->newStatus}].");
        }

        $endsOn = $data->endsOn ?? Carbon::today();

        return $this->transaction(function () use ($beneficiary, $data, $endsOn): SponsorshipBeneficiary {
            $beneficiary->update(['status' => $data->newStatus, 'ends_on' => $endsOn->toDateString()]);

            if ($beneficiary->fee_liability_id !== null) {
                FeeLiability::query()->whereKey($beneficiary->fee_liability_id)->update(['is_active' => false, 'effective_to' => $endsOn->toDateString()]);
            }

            return $beneficiary->fresh();
        });
    }
}
