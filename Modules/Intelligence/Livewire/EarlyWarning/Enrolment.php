<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\EarlyWarning;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\GenerateEnrolmentForecastAction;
use Modules\Intelligence\Domain\Actions\GetCapacityPlanningProjectionAction;
use Modules\Intelligence\Models\EnrolmentForecast;

/**
 * `Intelligence\EarlyWarning\Enrolment` (Book J INT-03 §5,
 * `executive.dashboard.view`). Forecast per grade for a target year,
 * with its confidence band and basis note shown — a forecast on thin
 * history is marked low-confidence, never hidden (BR-INT-03-007) — and
 * the capacity projection read from hostel and venue capacity
 * (BR-INT-03-012). Generating forecasts needs `risk.configure`.
 */
#[Title('Enrolment forecast')]
#[Layout('layouts.app')]
final class Enrolment extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $academicYearId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('executive.dashboard.view');

        $this->academicYearId = AcademicYear::where('school_id', $school->id)->orderByDesc('starts_on')->value('id');
    }

    public function generate(): void
    {
        $this->authorizePermission('risk.configure');

        $year = $this->selectedYear();

        if ($year === null) {
            $this->toast(__('Choose a target academic year first.'), 'danger');

            return;
        }

        foreach (GradeLevel::where('school_id', $this->school->id)->pluck('id') as $gradeLevelId) {
            app(GenerateEnrolmentForecastAction::class)->execute($this->school->id, $year->id, (int) $gradeLevelId);
        }

        $this->toast(__('Forecast generated.'));
    }

    private function selectedYear(): ?AcademicYear
    {
        return $this->academicYearId === null ? null : AcademicYear::where('school_id', $this->school->id)->find($this->academicYearId);
    }

    public function render(): View
    {
        $year = $this->selectedYear();
        $user = auth()->user();
        $forecasts = $year === null ? collect() : EnrolmentForecast::where('school_id', $this->school->id)->where('academic_year_id', $year->id)->get();

        return view('intelligence::early-warning.enrolment', [
            'years' => AcademicYear::where('school_id', $this->school->id)->orderByDesc('starts_on')->limit(8)->get(['id', 'name']),
            'forecasts' => $forecasts,
            'gradeNames' => GradeLevel::where('school_id', $this->school->id)->whereIn('id', $forecasts->pluck('grade_level_id'))->pluck('name', 'id'),
            'capacity' => $year === null ? null : app(GetCapacityPlanningProjectionAction::class)->execute($this->school->id, $year->id),
            'canGenerate' => $user !== null && app(PermissionScopeResolver::class)->has($user, 'risk.configure', PermissionScope::Own),
        ]);
    }
}
