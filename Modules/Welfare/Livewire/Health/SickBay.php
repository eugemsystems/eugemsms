<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Health;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\AdmitToSickBayAction;
use Modules\Welfare\Domain\Actions\DischargeSickBayAction;
use Modules\Welfare\Domain\Actions\RecordClinicObservationAction;
use Modules\Welfare\Domain\DataObjects\AdmitToSickBayData;
use Modules\Welfare\Domain\DataObjects\RecordClinicObservationData;
use Modules\Welfare\Models\SickBayAdmission;

/**
 * `Health\SickBay` (Book G BRD-06 §5, `health.admission.manage` —
 * Tier 3). Folds the spec's separate "Observations" screen into the
 * admission lifecycle, the same way `RollCall\Incident` folds its own
 * related records. Closes the `sick_bay` roll-status stub — admitting
 * here is all `BRD-02`'s `OpenRollCallAction` needs.
 */
#[Title('Sick bay')]
#[Layout('layouts.app')]
final class SickBay extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $presentingComplaint = '';

    public string $severity = 'minor';

    public bool $isIsolation = false;

    public ?string $isolationReason = null;

    public ?int $viewingAdmissionId = null;

    public ?float $temperatureC = null;

    public ?int $pulseBpm = null;

    public ?string $notes = null;

    public string $dischargeDestination = 'hostel';

    public ?string $dischargeNotes = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('health.admission.manage');
    }

    public function admit(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'presentingComplaint' => ['required', 'string', 'max:255'],
            'severity' => ['required', 'string'],
        ]);

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        app(AdmitToSickBayAction::class)->execute(new AdmitToSickBayData(
            schoolId: $this->school->id,
            termId: $term->id,
            studentId: (int) $this->studentId,
            admittedAt: Carbon::now(),
            admittedByUserId: (int) Auth::id(),
            presentingComplaint: $this->presentingComplaint,
            severity: $this->severity,
            isIsolation: $this->isIsolation,
            isolationReason: $this->isolationReason,
        ));

        $this->reset(['studentId', 'presentingComplaint', 'isolationReason']);
        $this->isIsolation = false;
        $this->toast(__('Admitted to sick bay.'));
    }

    public function observe(int $admissionId): void
    {
        app(RecordClinicObservationAction::class)->execute(new RecordClinicObservationData(
            schoolId: $this->school->id,
            admissionId: $admissionId,
            observedAt: Carbon::now(),
            observedByUserId: (int) Auth::id(),
            temperatureC: $this->temperatureC,
            pulseBpm: $this->pulseBpm,
            notes: $this->notes,
        ));

        $this->reset(['temperatureC', 'pulseBpm', 'notes']);
        $this->toast(__('Observation recorded.'));
    }

    public function discharge(int $admissionId): void
    {
        app(DischargeSickBayAction::class)->execute($admissionId, (int) Auth::id(), $this->dischargeDestination, $this->dischargeNotes);

        $this->reset(['dischargeNotes', 'viewingAdmissionId']);
        $this->toast(__('Discharged.'));
    }

    public function render(): View
    {
        return view('welfare::health.sick-bay', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'admissions' => SickBayAdmission::where('school_id', $this->school->id)
                ->whereIn('status', SickBayAdmission::CURRENTLY_ADMITTED_STATUSES)
                ->with(['student:id,first_name,last_name', 'observations' => fn ($q) => $q->orderByDesc('observed_at')->limit(5)])
                ->orderByDesc('admitted_at')
                ->get(),
        ]);
    }
}
