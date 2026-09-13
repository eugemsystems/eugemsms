<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Fees;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;
use Modules\Finance\Domain\Exceptions\AdHocChargeRequiresApprovalException;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Models\Student;

/**
 * `Finance\Fees\AdHocCharge` (Book B FIN-02 §7, `finance.ad_hoc.create`)
 * — a one-off charge, individually or bulk-to-class. Above
 * `finance.ad_hoc_approval_threshold_minor`, `CreateAdHocChargeAction`
 * refuses without an `approvedByUserId` (BR-FIN-02-019); this screen's
 * own self-approve checkbox only appears for a user who separately
 * holds `finance.ad_hoc.approve` — the same "different permission,
 * different person" split Book B FIN-01's `journal.approve` uses.
 */
#[Title('Ad hoc charge')]
#[Layout('layouts.app')]
final class AdHocCharge extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $mode = 'individual';

    public string $studentSearch = '';

    public ?int $selectedStudentId = null;

    public string $selectedStudentLabel = '';

    public ?int $classId = null;

    public ?int $componentId = null;

    public string $description = '';

    public string $quantity = '1';

    public string $unitRate = '';

    public string $currency = 'USD';

    public bool $selfApprove = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('finance.ad_hoc.create');
    }

    public function canSelfApprove(): bool
    {
        $user = Auth::user();

        return $user !== null
            && app(PermissionScopeResolver::class)->has($user, 'finance.ad_hoc.approve', PermissionScope::Own, $this->school->id);
    }

    /**
     * @return Collection<int, Student>
     */
    public function studentResults(): Collection
    {
        if (mb_strlen($this->studentSearch) < 2) {
            return new Collection;
        }

        return Student::query()
            ->where(fn ($q) => $q->where('admission_number', 'like', "%{$this->studentSearch}%")
                ->orWhere('first_name', 'like', "%{$this->studentSearch}%")
                ->orWhere('last_name', 'like', "%{$this->studentSearch}%"))
            ->limit(10)
            ->get();
    }

    public function selectStudent(int $studentId): void
    {
        $student = Student::findOrFail($studentId);
        $this->selectedStudentId = $student->id;
        $this->selectedStudentLabel = "{$student->admission_number} — {$student->fullName()}";
        $this->studentSearch = '';
    }

    public function raise(): void
    {
        $this->validate([
            'componentId' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unitRate' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'currency' => ['required', 'size:3'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->addError('description', __('No active academic year/term is set for this school.'));

            return;
        }

        // 'enrolled', not 'active', is a normal admission's ongoing
        // status for its whole stay — see ComputeBillingRunAction's own
        // comment on this exact gotcha.
        $students = $this->mode === 'individual'
            ? ($this->selectedStudentId !== null ? Student::where('id', $this->selectedStudentId)->get() : new Collection)
            : Student::where('school_id', $this->school->id)->where('class_id', $this->classId)->whereIn('status', ['enrolled', 'active', 'suspended'])->get();

        if ($students->isEmpty()) {
            $this->addError('description', $this->mode === 'individual' ? __('Select a student first.') : __('That class has no active students.'));

            return;
        }

        $unitRateMinor = (int) round((float) $this->unitRate * 100);
        $raised = 0;
        $blocked = 0;

        foreach ($students as $student) {
            try {
                app(CreateAdHocChargeAction::class)->execute(new CreateAdHocChargeData(
                    schoolId: $this->school->id,
                    academicYearId: $yearId,
                    termId: $termId,
                    studentId: $student->id,
                    componentId: (int) $this->componentId,
                    description: $this->description,
                    unitRateMinor: $unitRateMinor,
                    currency: $this->currency,
                    raisedByUserId: (int) Auth::id(),
                    quantity: $this->quantity,
                    approvedByUserId: $this->selfApprove && $this->canSelfApprove() ? (int) Auth::id() : null,
                ));
                $raised++;
            } catch (AdHocChargeRequiresApprovalException) {
                $blocked++;
            }
        }

        if ($blocked > 0) {
            $this->toast(__(':raised charge(s) raised, :blocked exceeded the approval threshold and were skipped — ask someone with approval rights to raise them.', ['raised' => $raised, 'blocked' => $blocked]), 'warning');
        } else {
            $this->toast(__(':count charge(s) raised.', ['count' => $raised]));
        }

        $this->reset(['description', 'quantity', 'unitRate', 'selectedStudentId', 'selectedStudentLabel']);
        $this->quantity = '1';
    }

    public function render(): View
    {
        return view('finance::fees.ad-hoc-charge', [
            'components' => FeeComponent::where('is_active', true)->orderBy('code')->get(),
            'classes' => SchoolClass::orderBy('name')->get(),
        ]);
    }
}
