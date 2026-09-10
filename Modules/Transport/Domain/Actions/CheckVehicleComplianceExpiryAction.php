<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Transport\Domain\Events\VehicleComplianceExpiring;
use Modules\Transport\Models\VehicleCompliance;

/**
 * ACT-CheckVehicleComplianceExpiry (Book H2 OPS-01 §4/BR-OPS-01-003).
 * Fires at 60/30/7 days, the same "fires exactly on the day crossed"
 * shape `FIN-10`'s own `CheckInsuranceExpiryAction` uses. Also flips
 * any row whose expiry has already passed to `expired` — the actual
 * trip-blocking check (`ScheduleTripAction`) reads `expires_on`
 * directly rather than trusting this cached status alone.
 */
final class CheckVehicleComplianceExpiryAction extends Action
{
    private const array ALERT_DAYS = [60, 30, 7];

    /**
     * @return Collection<int, VehicleCompliance>
     */
    public function execute(int $schoolId): Collection
    {
        $today = Carbon::now()->toDateString();
        $maxDays = max(self::ALERT_DAYS);

        VehicleCompliance::where('school_id', $schoolId)
            ->where('status', '!=', 'expired')
            ->whereDate('expires_on', '<', $today)
            ->update(['status' => 'expired']);

        $expiring = VehicleCompliance::query()
            ->where('school_id', $schoolId)
            ->where('status', 'valid')
            ->whereDate('expires_on', '>=', $today)
            ->whereDate('expires_on', '<=', Carbon::parse($today)->addDays($maxDays))
            ->get()
            ->filter(function (VehicleCompliance $compliance) use ($today): bool {
                $daysRemaining = Carbon::parse($today)->diffInDays($compliance->expires_on, false);

                return in_array((int) $daysRemaining, self::ALERT_DAYS, true);
            });

        foreach ($expiring as $compliance) {
            $daysRemaining = (int) Carbon::parse($today)->diffInDays($compliance->expires_on, false);
            event(new VehicleComplianceExpiring($compliance, $daysRemaining));
        }

        return $expiring;
    }
}
