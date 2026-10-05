<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Executive;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\SetKpiTargetAction;
use Modules\Intelligence\Domain\DataObjects\KpiDefinitionEntry;
use Modules\Intelligence\Domain\Registry\KpiRegistry;
use Modules\Intelligence\Models\KpiTarget;

/**
 * `Intelligence\Executive\Kpis` (Book J INT-02 §4, `executive.kpi.manage`).
 * A school's own target and warning threshold for each registered KPI,
 * for the *current academic year*; with none set, the KPI's system
 * default applies (BR-INT-02-002). The warning threshold is on the
 * KPI's own scale, compared directly with the current value — for a
 * 92% collection-rate target, a 90 threshold makes 87% red and 91%
 * amber (AC-INT-02-001) — so it must be set deliberately for a KPI that
 * is not a percentage (e.g. days overdue). The KPI key comes from the
 * registry, never from the request.
 */
#[Title('KPI targets')]
#[Layout('layouts.app')]
final class Kpis extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<string, array{target: string, warning: string}> */
    public array $rows = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('executive.kpi.manage');

        $this->loadRows();
    }

    public function save(string $kpiKey): void
    {
        $this->authorizePermission('executive.kpi.manage');

        abort_unless(KpiRegistry::get($kpiKey) !== null, 404);

        $year = $this->currentYear();

        if ($year === null) {
            $this->toast(__('There is no current academic year to set targets for.'), 'danger');

            return;
        }

        $this->validate([
            "rows.{$kpiKey}.target" => ['required', 'numeric', 'min:0', 'max:1000000000'],
            "rows.{$kpiKey}.warning" => ['required', 'numeric', 'min:0', 'max:1000000000'],
        ]);

        app(SetKpiTargetAction::class)->execute(
            $this->school->id,
            $kpiKey,
            $year->id,
            (float) $this->rows[$kpiKey]['target'],
            (float) $this->rows[$kpiKey]['warning'],
        );

        $this->toast(__('Target saved for :year.', ['year' => $year->name]));
    }

    public function render(): View
    {
        $year = $this->currentYear();
        $overrides = $year !== null
            ? KpiTarget::where('school_id', $this->school->id)->where('academic_year_id', $year->id)->get()->keyBy('kpi_key')
            : collect();

        return view('intelligence::executive.kpis', [
            'year' => $year,
            'kpis' => array_values(KpiRegistry::all()),
            'overrides' => $overrides,
        ]);
    }

    private function currentYear(): ?AcademicYear
    {
        return AcademicYear::where('school_id', $this->school->id)->where('is_current', true)->first();
    }

    private function loadRows(): void
    {
        $year = $this->currentYear();
        $overrides = $year !== null
            ? KpiTarget::where('school_id', $this->school->id)->where('academic_year_id', $year->id)->get()->keyBy('kpi_key')
            : collect();

        $this->rows = collect(KpiRegistry::all())->mapWithKeys(function (KpiDefinitionEntry $kpi) use ($overrides): array {
            if ($overrides->has($kpi->key)) {
                $override = $overrides[$kpi->key];

                return [$kpi->key => ['target' => (string) $override->target_value, 'warning' => (string) $override->warning_threshold_percent]];
            }

            return [$kpi->key => ['target' => (string) ($kpi->defaultTargetValue ?? ''), 'warning' => '90']];
        })->all();
    }
}
