<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\Events\InsuranceExpiring;
use Modules\Stores\Models\AssetInsurance;

/**
 * ACT-CheckInsuranceExpiry (Book H1 FIN-10 §6/BR-FIN-10-015). Fires
 * at 60/30/7 days, the same "fires exactly on the day crossed" shape
 * used throughout this module's other expiry checks.
 */
final class CheckInsuranceExpiryAction extends Action
{
    private const array ALERT_DAYS = [60, 30, 7];

    /**
     * @return Collection<int, AssetInsurance>
     */
    public function execute(int $schoolId): Collection
    {
        $today = Carbon::now()->toDateString();
        $maxDays = max(self::ALERT_DAYS);

        $expiring = AssetInsurance::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereDate('expires_on', '>=', $today)
            ->whereDate('expires_on', '<=', Carbon::parse($today)->addDays($maxDays))
            ->get()
            ->filter(function (AssetInsurance $policy) use ($today): bool {
                $daysRemaining = Carbon::parse($today)->diffInDays($policy->expires_on, false);

                return in_array((int) $daysRemaining, self::ALERT_DAYS, true);
            });

        foreach ($expiring as $policy) {
            $daysRemaining = (int) Carbon::parse($today)->diffInDays($policy->expires_on, false);
            event(new InsuranceExpiring($policy, $daysRemaining));
        }

        return $expiring;
    }
}
