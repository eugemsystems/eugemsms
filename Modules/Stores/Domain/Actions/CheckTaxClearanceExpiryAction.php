<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Stores\Domain\Events\TaxClearanceExpiring;
use Modules\Stores\Models\SupplierTaxClearance;

/**
 * ACT-CheckTaxClearanceExpiry (Book H1 FIN-08 §6/BR-FIN-08-004). Fires
 * once per clearance per configured alert window it has just crossed
 * — the same "days-to-expiry matches a threshold exactly on the day
 * checked" shape as `FIN-09`'s `CheckExpiringLotsAction`, meant to be
 * called once a day by a scheduled command.
 */
final class CheckTaxClearanceExpiryAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return Collection<int, SupplierTaxClearance>
     */
    public function execute(int $schoolId): Collection
    {
        $scope = new ScopeChain(schoolId: $schoolId);
        /** @var array<int, int> $alertDays */
        $alertDays = (array) $this->settings->get('procurement.clearance_alert_days', $scope);
        $today = Carbon::now()->toDateString();
        $maxDays = $alertDays === [] ? 0 : max($alertDays);

        $expiring = SupplierTaxClearance::query()
            ->where('school_id', $schoolId)
            ->where('status', 'valid')
            ->whereDate('expires_on', '>=', $today)
            ->whereDate('expires_on', '<=', Carbon::parse($today)->addDays($maxDays))
            ->get()
            ->filter(function (SupplierTaxClearance $clearance) use ($alertDays, $today): bool {
                $daysRemaining = Carbon::parse($today)->diffInDays($clearance->expires_on, false);

                return in_array((int) $daysRemaining, $alertDays, true);
            });

        foreach ($expiring as $clearance) {
            $daysRemaining = (int) Carbon::parse($today)->diffInDays($clearance->expires_on, false);
            event(new TaxClearanceExpiring($clearance, $daysRemaining));
        }

        return $expiring;
    }
}
