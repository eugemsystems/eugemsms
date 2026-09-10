<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\Events\ContractExpiring;
use Modules\Stores\Models\SupplierContract;

/**
 * ACT-CheckContractExpiry (Book H1 FIN-08 §6/BR-FIN-08-024). An
 * auto-renewing contract still alerts before its own renewal-notice
 * window closes — auto-renew means "renews unless cancelled", not
 * "never needs a human to look at it again".
 */
final class CheckContractExpiryAction extends Action
{
    /**
     * @return Collection<int, SupplierContract>
     */
    public function execute(int $schoolId): Collection
    {
        $today = Carbon::now()->toDateString();

        $expiring = SupplierContract::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', ['active', 'expiring'])
            ->whereNotNull('ends_on')
            ->whereNotNull('renewal_notice_days')
            ->get()
            ->filter(function (SupplierContract $contract) use ($today): bool {
                $daysRemaining = (int) Carbon::parse($today)->diffInDays($contract->ends_on, false);

                return $daysRemaining === (int) $contract->renewal_notice_days;
            });

        foreach ($expiring as $contract) {
            $contract->update(['status' => 'expiring']);
            event(new ContractExpiring($contract, (int) $contract->renewal_notice_days));
        }

        return $expiring;
    }
}
