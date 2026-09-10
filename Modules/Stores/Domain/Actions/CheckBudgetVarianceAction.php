<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Stores\Domain\Events\BudgetExceeded;
use Modules\Stores\Models\BudgetLine;

/**
 * ACT-CheckBudgetVariance (Book H1 FIN-11 §6/BR-FIN-11-017/
 * AC-FIN-11-007). Fires when a line's committed-plus-actual position
 * has moved more than `budget.variance_alert_percent` beyond its own
 * annual amount — i.e. `available` has gone negative by more than
 * that tolerance, not merely touched zero.
 */
final class CheckBudgetVarianceAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return Collection<int, BudgetLine>
     */
    public function execute(int $schoolId): Collection
    {
        $thresholdPercent = (int) $this->settings->get('budget.variance_alert_percent', new ScopeChain(schoolId: $schoolId));

        $exceeded = BudgetLine::query()
            ->where('school_id', $schoolId)
            ->where('annual_amount_minor', '>', 0)
            ->get()
            ->filter(function (BudgetLine $line) use ($thresholdPercent): bool {
                $consumed = $line->committed_minor + $line->actual_minor;
                $toleratedMinor = (int) round($line->annual_amount_minor * (1 + $thresholdPercent / 100));

                return $consumed > $toleratedMinor;
            });

        foreach ($exceeded as $line) {
            event(new BudgetExceeded($line, $line->committed_minor + $line->actual_minor, $line->available_minor));
        }

        return $exceeded;
    }
}
