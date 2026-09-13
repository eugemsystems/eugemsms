<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Suspense;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ResolveSuspenseItemAction;
use Modules\Finance\Domain\DataObjects\ResolveSuspenseItemData;
use Modules\Finance\Livewire\Concerns\ResolvesSystemAccounts;
use Modules\Finance\Models\SuspenseItem;
use Modules\People\Models\Student;

/**
 * `Finance\Suspense\Workbench` (Book B FIN-04 §2/§4/BR-FIN-04-010/011,
 * `finance.suspense.manage`). An aged queue of unidentified deposits —
 * `age_days` is a declared column nothing populates, so age is
 * computed here straight from `deposit_date` rather than trusted from
 * an always-`0` cache. A cashier picks a learner and confirms the
 * match; `ResolveSuspenseItemAction` is the only path that ever
 * allocates a suspense item — never automatic.
 */
#[Title('Suspense workbench')]
#[Layout('layouts.app')]
final class Workbench extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesSystemAccounts;

    public ?int $resolvingItemId = null;

    public string $studentSearch = '';

    public ?int $selectedStudentId = null;

    public string $selectedStudentLabel = '';

    public string $resolutionNote = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.suspense.manage');
    }

    public function startResolving(int $suspenseItemId): void
    {
        $this->resolvingItemId = $suspenseItemId;
        $this->studentSearch = '';
        $this->selectedStudentId = null;
        $this->selectedStudentLabel = '';
        $this->resolutionNote = '';
        $this->resetErrorBag();
    }

    public function cancelResolving(): void
    {
        $this->resolvingItemId = null;
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

    public function resolve(): void
    {
        $this->validate([
            'selectedStudentId' => ['required', 'integer'],
        ]);

        try {
            app(ResolveSuspenseItemAction::class)->execute(new ResolveSuspenseItemData(
                suspenseItemId: (int) $this->resolvingItemId,
                studentId: (int) $this->selectedStudentId,
                resolvedByUserId: (int) Auth::id(),
                suspenseAccountId: $this->requireSystemAccount('suspense', __('Suspense')),
                resolutionNote: $this->resolutionNote !== '' ? $this->resolutionNote : null,
            ));
        } catch (DomainException $e) {
            $this->addError('selectedStudentId', $e->getMessage());

            return;
        }

        $this->resolvingItemId = null;
    }

    /**
     * @return Collection<int, SuspenseItem>
     */
    public function items(): Collection
    {
        return SuspenseItem::query()
            ->where('status', 'unidentified')
            ->orderBy('deposit_date')
            ->get();
    }

    public function render(): View
    {
        return view('finance::suspense.workbench');
    }
}
