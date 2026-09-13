<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\DeactivateFeeLiabilityData;
use Modules\People\Domain\Events\LiabilityChanged;
use Modules\People\Models\FeeLiability;

/**
 * ACT-DeactivateFeeLiability (Book C PPL-03 §3, backing
 * `Finance\Liabilities\Editor` — Book B FIN-03 §5's own
 * `finance.liability.manage` screen). `LiabilityResolver` only ever
 * reads `is_active = true` rules, so deactivating one (rather than
 * deleting it) is enough to stop it applying to any future
 * resolution while leaving it in place for whatever historical
 * invoices it already produced to still make sense against.
 */
final class DeactivateFeeLiabilityAction extends Action
{
    public function execute(DeactivateFeeLiabilityData $data): FeeLiability
    {
        $liability = FeeLiability::findOrFail($data->feeLiabilityId);

        return $this->transaction(function () use ($liability): FeeLiability {
            $liability->update(['is_active' => false, 'effective_to' => Carbon::now()->toDateString()]);

            event(new LiabilityChanged($liability));

            return $liability;
        });
    }
}
