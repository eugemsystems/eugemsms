<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Catering;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Models\MealService;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Catering\Costs` (Book F BRD-04 §4, `boarding.catering.service.view`). What catering costs and how
 * much was over-produced, over a date range, from closed services. The cost of a meal is its priced
 * ingredients (store standard cost, else the latest lot) scaled to the servings actually served, so
 * it is an estimate at current stock cost, not a ledger figure. A service with any unpriced
 * ingredient has no cost and is counted separately — never shown as zero. Over-production is planned
 * servings above those served.
 */
#[Title('Catering costs')]
#[Layout('layouts.app')]
final class Costs extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $from = '';

    public string $to = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.catering.service.view');

        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
    }

    public function render(): View
    {
        $this->authorizePermission('boarding.catering.service.view');
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);

        $services = MealService::query()->where('status', 'closed')
            ->whereDate('service_date', '>=', Carbon::parse($this->from)->toDateString())->whereDate('service_date', '<=', Carbon::parse($this->to)->toDateString())
            ->orderBy('service_date')->get();

        return view('boarding::catering.costs', [
            'services' => $services,
            'byMeal' => $this->byMeal($services),
            'weekly' => $this->weekly($services),
            'unpriced' => $services->whereNull('cost_per_serving_minor')->count(),
            'currency' => $this->school->base_currency,
        ]);
    }

    /**
     * @param  Collection<int, MealService>  $services
     * @return list<array{meal: string, services: int, served: int, cost_minor: int, per_serving_minor: int|null, planned: int, over_percent: float|null}>
     */
    private function byMeal(Collection $services): array
    {
        $rows = [];

        foreach ($services->groupBy('meal') as $meal => $group) {
            $costed = $group->whereNotNull('issued_cost_minor');
            $served = (int) $costed->sum('actual_served');
            $cost = (int) $costed->sum('issued_cost_minor');
            $planned = (int) $group->sum('planned_servings');
            $actual = (int) $group->sum('actual_served');

            $rows[] = [
                'meal' => (string) $meal,
                'services' => $group->count(),
                'served' => $actual,
                'cost_minor' => $cost,
                'per_serving_minor' => $served > 0 ? (int) round($cost / $served) : null,
                'planned' => $planned,
                'over_percent' => $actual > 0 ? round(max(0, $planned - $actual) / $actual * 100, 1) : null,
            ];
        }

        return $rows;
    }

    /**
     * Cost per serving by week (Monday), over services that could be costed.
     *
     * @param  Collection<int, MealService>  $services
     * @return list<array{week: string, per_serving_minor: int}>
     */
    private function weekly(Collection $services): array
    {
        $rows = [];

        foreach ($services->whereNotNull('issued_cost_minor')->groupBy(fn (MealService $s): string => $s->service_date->copy()->startOfWeek()->toDateString()) as $week => $group) {
            $served = (int) $group->sum('actual_served');

            if ($served > 0) {
                $rows[] = ['week' => (string) $week, 'per_serving_minor' => (int) round($group->sum('issued_cost_minor') / $served)];
            }
        }

        return $rows;
    }
}
