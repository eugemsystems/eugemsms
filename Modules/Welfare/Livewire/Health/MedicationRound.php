<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Health;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\AdministerMedicationAction;
use Modules\Welfare\Domain\DataObjects\AdministerMedicationData;
use Modules\Welfare\Models\ClinicStock;
use Modules\Welfare\Models\Prescription;

/**
 * `Health\MedicationRound` (Book G BRD-06 §5 ⭐⭐, `health.medication.administer`
 * — Tier 3). The one-tap record screen: pick a student, pick an active
 * prescription (or none for an OTC/standing-consent dose), optionally
 * select clinic stock, record. Every refusal — missing consent, expired
 * stock, missing witness for controlled stock — is the real server-side
 * refusal from `AdministerMedicationAction` itself, surfaced as a toast;
 * this screen adds no client-side consent/expiry logic of its own.
 */
#[Title('Medication round')]
#[Layout('layouts.app')]
final class MedicationRound extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public ?int $prescriptionId = null;

    public string $medicationName = '';

    public string $dose = '';

    public string $route = 'oral';

    public ?int $clinicStockId = null;

    public float $stockQuantityConsumed = 1.0;

    public ?int $witnessedByUserId = null;

    public string $outcome = 'given';

    public ?string $omissionReason = null;

    public bool $emergencyProvision = false;

    public ?int $emergencyDecisionMakerUserId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('health.medication.administer');
    }

    public function updatedPrescriptionId(string $value): void
    {
        $prescription = $value !== '' ? Prescription::find((int) $value) : null;

        if ($prescription !== null) {
            $this->medicationName = $prescription->medication_name;
            $this->dose = $prescription->dose;
            $this->route = $prescription->route;
        }
    }

    public function administer(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'medicationName' => ['required', 'string'],
            'dose' => ['required', 'string'],
            'route' => ['required', 'string'],
            'outcome' => ['required', 'string'],
        ]);

        try {
            app(AdministerMedicationAction::class)->execute(new AdministerMedicationData(
                schoolId: $this->school->id,
                studentId: (int) $this->studentId,
                medicationName: $this->medicationName,
                dose: $this->dose,
                route: $this->route,
                administeredAt: Carbon::now(),
                administeredByUserId: (int) Auth::id(),
                prescriptionId: $this->prescriptionId,
                clinicStockId: $this->clinicStockId,
                stockQuantityConsumed: $this->stockQuantityConsumed,
                witnessedByUserId: $this->witnessedByUserId,
                outcome: $this->outcome,
                omissionReason: $this->outcome !== 'given' ? $this->omissionReason : null,
                emergencyProvision: $this->emergencyProvision,
                emergencyDecisionMakerUserId: $this->emergencyProvision ? ((int) Auth::id()) : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->reset(['prescriptionId', 'medicationName', 'dose', 'clinicStockId', 'witnessedByUserId', 'omissionReason', 'emergencyProvision']);
        $this->outcome = 'given';
        $this->toast(__('Administration recorded.'));
    }

    public function render(): View
    {
        return view('welfare::health.medication-round', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'prescriptions' => $this->studentId !== null
                ? Prescription::where('student_id', $this->studentId)->where('status', 'active')->get()
                : collect(),
            'stock' => ClinicStock::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
