<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Health;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\CreatePrescriptionAction;
use Modules\Welfare\Domain\DataObjects\CreatePrescriptionData;
use Modules\Welfare\Models\MedicalConsent;
use Modules\Welfare\Models\Prescription;

/**
 * `Health\Prescriptions` (Book G BRD-06 §5, `health.medication.manage`
 * — Tier 3). Create-only — no update action exists in the domain layer.
 * Refuses, via `CreatePrescriptionAction` itself, without a valid
 * `prescribed_medication` consent (BR-BRD-06-012).
 */
#[Title('Prescriptions')]
#[Layout('layouts.app')]
final class Prescriptions extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $medicationName = '';

    public string $dose = '';

    public string $frequency = '';

    public string $route = 'oral';

    public string $prescribedBy = '';

    public string $prescribedOn = '';

    public string $startsOn = '';

    public ?string $endsOn = null;

    public ?int $guardianConsentId = null;

    public bool $isPrn = false;

    public bool $isSelfAdministered = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('health.medication.manage');

        $this->prescribedOn = now()->toDateString();
        $this->startsOn = now()->toDateString();
    }

    public function create(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'medicationName' => ['required', 'string'],
            'dose' => ['required', 'string'],
            'frequency' => ['required', 'string'],
            'prescribedBy' => ['required', 'string'],
            'guardianConsentId' => ['required', 'integer'],
        ]);

        try {
            app(CreatePrescriptionAction::class)->execute(new CreatePrescriptionData(
                schoolId: $this->school->id,
                studentId: (int) $this->studentId,
                medicationName: $this->medicationName,
                dose: $this->dose,
                frequency: $this->frequency,
                route: $this->route,
                prescribedBy: $this->prescribedBy,
                prescribedOn: Carbon::parse($this->prescribedOn),
                startsOn: Carbon::parse($this->startsOn),
                guardianConsentId: (int) $this->guardianConsentId,
                endsOn: $this->endsOn !== null && $this->endsOn !== '' ? Carbon::parse($this->endsOn) : null,
                isPrn: $this->isPrn,
                isSelfAdministered: $this->isSelfAdministered,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['medicationName', 'dose', 'frequency', 'endsOn']);
        $this->toast(__('Prescription created.'));
    }

    public function render(): View
    {
        return view('welfare::health.prescriptions', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'consents' => $this->studentId !== null
                ? MedicalConsent::where('student_id', $this->studentId)->where('consent_type', 'prescribed_medication')->where('granted', true)->whereNull('withdrawn_at')->get()
                : collect(),
            'prescriptions' => Prescription::where('school_id', $this->school->id)->with('student:id,first_name,last_name')->orderByDesc('id')->limit(100)->get(),
        ]);
    }
}
