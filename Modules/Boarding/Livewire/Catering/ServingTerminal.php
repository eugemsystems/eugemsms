<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Catering;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\GetDietaryAlertsAction;
use Modules\Boarding\Domain\Actions\RecordMealAttendanceAction;
use Modules\Boarding\Domain\DataObjects\RecordMealAttendanceData;
use Modules\Boarding\Domain\Support\DietaryAlert;
use Modules\Boarding\Models\MealAttendance;
use Modules\Boarding\Models\MealService;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Catering\ServingTerminal` (Book F BRD-04 §6 ⭐, `catering.serve`).
 * Scan a learner, show dietary alerts in large type with their
 * photograph, at every meal — not only the first (BR-BRD-04-009/011).
 * `GetDietaryAlertsAction` runs on every scan; nothing here caches a
 * "already shown once" state.
 *
 * **Gap closed**: attendance/special-meal confirmation
 * (`RecordMealAttendanceAction`, BR-BRD-04-015) — the backend Action,
 * data object and model existed with nothing calling them. Capture is
 * offered here, against the service selected above, only when
 * `catering.meal_attendance_capture` is enabled for the school; where
 * disabled, `Catering\ServicePlan`'s own manual `actual_served` entry
 * is unchanged, exactly per the rule's own wording.
 */
#[Title('Serving terminal')]
#[Layout('layouts.app')]
final class ServingTerminal extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $search = '';

    public ?Student $scannedStudent = null;

    /** @var Collection<int, DietaryAlert> */
    public Collection $alerts;

    public string $serviceDate = '';

    public string $meal = 'lunch';

    public bool $captureEnabled = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.catering.serve');
        $this->alerts = collect();
        $this->serviceDate = now()->toDateString();
        $this->captureEnabled = (bool) app(SettingResolver::class)->get('catering.meal_attendance_capture', new ScopeChain(schoolId: $school->id));
    }

    public function scan(int $studentId): void
    {
        $this->scannedStudent = Student::findOrFail($studentId);
        $this->alerts = app(GetDietaryAlertsAction::class)->execute($studentId);
    }

    public function confirmServed(bool $specialMealServed): void
    {
        $this->authorizePermission('boarding.catering.serve');

        $service = $this->currentService();

        if ($this->scannedStudent === null || $service === null) {
            $this->toast(__('No service planned for this date and meal yet.'), 'danger');

            return;
        }

        app(RecordMealAttendanceAction::class)->execute(new RecordMealAttendanceData(
            mealServiceId: $service->id,
            studentId: $this->scannedStudent->id,
            attended: true,
            specialMealServed: $specialMealServed,
            method: 'manual',
        ));

        $this->toast(__(':name recorded as served.', ['name' => $this->scannedStudent->first_name]));
        $this->reset(['scannedStudent', 'alerts', 'search']);
        $this->alerts = collect();
    }

    public function render(): View
    {
        $results = $this->search !== ''
            ? Student::where('school_id', $this->school->id)
                ->where(fn ($q) => $q->where('first_name', 'like', "%{$this->search}%")->orWhere('last_name', 'like', "%{$this->search}%")->orWhere('admission_number', 'like', "%{$this->search}%"))
                ->limit(10)->get()
            : collect();

        $service = $this->currentService();

        return view('boarding::catering.serving-terminal', [
            'results' => $results,
            'service' => $service,
            'servedCount' => $service === null ? 0 : MealAttendance::where('meal_service_id', $service->id)->where('attended', true)->count(),
            'alreadyServed' => $service === null || $this->scannedStudent === null
                ? false
                : MealAttendance::where('meal_service_id', $service->id)->where('student_id', $this->scannedStudent->id)->where('attended', true)->exists(),
        ]);
    }

    private function currentService(): ?MealService
    {
        return MealService::where('school_id', $this->school->id)
            ->whereDate('service_date', Carbon::parse($this->serviceDate)->toDateString())
            ->where('meal', $this->meal)
            ->first();
    }
}
