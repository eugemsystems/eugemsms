<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Modules\Core\Models\AcademicYear;
use Modules\Intelligence\Domain\Actions\GetKpiValueAction;
use Modules\Intelligence\Domain\DataObjects\KpiDefinitionEntry;
use Modules\Intelligence\Domain\Registry\KpiRegistry;

/**
 * KPI tiles for the executive dashboards. Each tile carries its
 * red/amber/green status from `GetKpiValueAction` (never a bare number
 * — BR-INT-02-003) and a drill-down link to the *owning module's own*
 * screen (BR-INT-02-007); a KPI with no such screen simply has no link
 * rather than a parallel detail view being invented here.
 */
trait BuildsKpiTiles
{
    /**
     * KPI key → the owning module's own route name.
     *
     * @var array<string, string>
     */
    private const array DRILL_DOWN_ROUTES = [
        'collection_rate' => 'finance.reports.collections',
        'average_days_overdue' => 'finance.reports.aged-debtors',
    ];

    /**
     * @param  string|null  $moduleCodePrefix  e.g. 'FIN' to keep only finance KPIs
     * @return array<int, array{key: string, label: string, unit: string, current: float, target: ?float, status: string, higherIsBetter: bool, url: ?string}>
     */
    protected function kpiTiles(?string $moduleCodePrefix = null): array
    {
        $year = AcademicYear::where('school_id', $this->school->id)->where('is_current', true)->first();

        if ($year === null) {
            return [];
        }

        $action = app(GetKpiValueAction::class);

        return collect(KpiRegistry::all())
            ->filter(fn (KpiDefinitionEntry $kpi): bool => $moduleCodePrefix === null || str_starts_with($kpi->moduleCode, $moduleCodePrefix))
            ->map(function (KpiDefinitionEntry $kpi) use ($action, $year): array {
                $result = $action->execute($kpi->key, $this->school->id, $year->id);
                $route = self::DRILL_DOWN_ROUTES[$kpi->key] ?? null;

                return [
                    'key' => $result->key,
                    'label' => $result->label,
                    'unit' => $result->unit,
                    'current' => $result->currentValue,
                    'target' => $result->targetValue,
                    'status' => $result->status,
                    'higherIsBetter' => $result->higherIsBetter,
                    'url' => $route !== null && Route::has($route) ? route($route, $this->school) : null,
                ];
            })->values()->all();
    }

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
