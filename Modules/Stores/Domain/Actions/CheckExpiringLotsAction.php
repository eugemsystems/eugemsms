<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Stores\Domain\Events\ExpiryApproaching;
use Modules\Stores\Models\StockLot;

/**
 * ACT-CheckExpiringLots (Book H1 FIN-09 §9/`inventory.expiry_alert_days`).
 * Fires one event per lot per configured alert window it has just
 * crossed (e.g. 90/30/7 days out) — a scheduled command is expected to
 * call this once a day, so "just crossed" means the lot's days-to-expiry
 * matches a configured threshold exactly on the day it is checked.
 */
final class CheckExpiringLotsAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return Collection<int, StockLot>
     */
    public function execute(int $schoolId): Collection
    {
        $scope = new ScopeChain(schoolId: $schoolId);
        /** @var array<int, int> $alertDays */
        $alertDays = (array) $this->settings->get('inventory.expiry_alert_days', $scope);
        $today = Carbon::now()->toDateString();
        $maxDays = $alertDays === [] ? 0 : max($alertDays);

        $expiring = StockLot::query()
            ->where('school_id', $schoolId)
            ->where('is_depleted', false)
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', Carbon::parse($today)->addDays($maxDays))
            ->get()
            ->filter(function (StockLot $lot) use ($alertDays, $today): bool {
                $daysRemaining = Carbon::parse($today)->diffInDays($lot->expiry_date, false);

                return in_array((int) $daysRemaining, $alertDays, true);
            });

        foreach ($expiring as $lot) {
            $daysRemaining = (int) Carbon::parse($today)->diffInDays($lot->expiry_date, false);
            event(new ExpiryApproaching($lot, $daysRemaining));
        }

        return $expiring;
    }
}
