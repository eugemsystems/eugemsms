<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\CreditNotes;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CreateCreditNoteAction;
use Modules\Finance\Domain\DataObjects\CreateCreditNoteData;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\Invoice;
use Modules\People\Models\Student;

/**
 * `Finance\CreditNotes\Create` (Book B FIN-03 §5/BR-FIN-03-010/011,
 * `finance.credit_note.create`). Above `finance.credit_note_approval_threshold_minor`,
 * `CreateCreditNoteAction` refuses without an approving user — this
 * screen's own self-approve checkbox only appears for a user who
 * separately holds `finance.credit_note.approve`, the same split
 * `Fees\AdHocCharge` already uses for its own threshold.
 */
#[Title('New credit note')]
#[Layout('layouts.app')]
final class Create extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;

    public string $studentSearch = '';

    public ?int $selectedStudentId = null;

    public string $selectedStudentLabel = '';

    public ?int $invoiceId = null;

    public string $reasonCode = 'billing_error';

    public string $reason = '';

    public string $currency = 'USD';

    /**
     * @var array<int, array{component_id: string, description: string, amount_minor: string}>
     */
    public array $lines = [];

    public bool $selfApprove = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('finance.credit_note.create');
        $this->addLine();
    }

    public function canSelfApprove(): bool
    {
        $user = Auth::user();

        return $user !== null
            && app(PermissionScopeResolver::class)->has($user, 'finance.credit_note.approve', PermissionScope::Own, $this->school->id);
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
        $this->invoiceId = null;
    }

    public function addLine(): void
    {
        $this->lines[] = ['component_id' => '', 'description' => '', 'amount_minor' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(): void
    {
        $this->validate([
            'reasonCode' => ['required', 'in:subject_dropped,withdrawal,billing_error,residency_change,goodwill,overcharge'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'currency' => ['required', 'size:3'],
            'lines' => ['array', 'min:1'],
            'lines.*.component_id' => ['required', 'integer'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.amount_minor' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);

        if ($this->selectedStudentId === null) {
            $this->addError('reason', __('Select a learner first.'));

            return;
        }

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->addError('reason', __('No active academic year/term is set for this school.'));

            return;
        }

        $lineData = array_map(fn (array $line): array => [
            'component_id' => (int) $line['component_id'],
            'description' => $line['description'],
            'amount_minor' => (int) round((float) $line['amount_minor'] * 100),
        ], $this->lines);

        try {
            $creditNote = app(CreateCreditNoteAction::class)->execute(new CreateCreditNoteData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                studentId: $this->selectedStudentId,
                reasonCode: $this->reasonCode,
                reason: $this->reason,
                currency: $this->currency,
                lines: $lineData,
                raisedByUserId: (int) Auth::id(),
                invoiceId: $this->invoiceId,
                approvedByUserId: $this->selfApprove && $this->canSelfApprove() ? (int) Auth::id() : null,
            ));
        } catch (DomainException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        $this->redirectRoute('finance.accounts.learner-account', ['school' => $this->school, 'student' => $creditNote->student], navigate: true);
    }

    public function render(): View
    {
        return view('finance::credit-notes.create', [
            'components' => FeeComponent::where('is_active', true)->orderBy('code')->get(),
            'invoices' => $this->selectedStudentId !== null
                ? Invoice::where('student_id', $this->selectedStudentId)->where('status', '!=', 'voided')->orderByDesc('id')->get()
                : collect(),
        ]);
    }
}
