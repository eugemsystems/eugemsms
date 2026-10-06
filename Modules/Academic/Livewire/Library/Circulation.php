<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Library;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\IssueLoanAction;
use Modules\Academic\Domain\Actions\RenewLoanAction;
use Modules\Academic\Domain\Actions\ReportLoanLostAction;
use Modules\Academic\Domain\Actions\ReturnLoanAction;
use Modules\Academic\Domain\DataObjects\IssueLoanData;
use Modules\Academic\Domain\DataObjects\RenewLoanData;
use Modules\Academic\Domain\DataObjects\ReportLoanLostData;
use Modules\Academic\Domain\DataObjects\ReturnLoanData;
use Modules\Academic\Models\BorrowerCategory;
use Modules\Academic\Models\LibraryCopy;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * `Academic\Library\Circulation` (Book K ACA-10 §5, `library.circulate`).
 * Scan-based issue, return, renew and lost. The picked copy and borrower are
 * client-tamperable, so every action re-resolves them through the
 * school-scoped models. A late return or a lost copy of a learner posts to the
 * fee account through FIN-02, so the desk must name the fee component; a
 * learner at their loan limit is refused with no override (AC-ACA-10-003).
 */
#[Title('Circulation desk')]
#[Layout('layouts.app')]
final class Circulation extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $copyCode = '';

    public string $borrowerType = 'student';

    public string $borrowerSearch = '';

    public ?int $borrowerId = null;

    public string $borrowerLabel = '';

    public string $borrowerCategory = '';

    public string $returnCode = '';

    public string $returnCondition = 'good';

    public ?int $feeComponentId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('library.circulate');
    }

    public function updatedBorrowerType(): void
    {
        $this->reset('borrowerSearch', 'borrowerId', 'borrowerLabel');
    }

    public function selectBorrower(int $borrowerId): void
    {
        $this->authorizePermission('library.circulate');
        $borrower = $this->resolveBorrower($borrowerId);

        if ($borrower === null) {
            $this->addError('borrowerSearch', __('That borrower could not be found.'));

            return;
        }

        $this->borrowerId = $borrowerId;
        $this->borrowerLabel = $borrower;
        $this->borrowerSearch = '';
    }

    public function issue(): void
    {
        $this->authorizePermission('library.circulate');
        $this->resetErrorBag();

        $copy = $this->findCopy($this->copyCode);
        $term = Term::query()->where('starts_on', '<=', now())->orderByDesc('starts_on')->first();

        if ($copy === null || $this->borrowerId === null || $this->resolveBorrower($this->borrowerId) === null) {
            $this->addError('copyCode', __('Scan a copy and choose a borrower.'));

            return;
        }

        if ($term === null || BorrowerCategory::query()->where('category', $this->borrowerCategory)->doesntExist()) {
            $this->addError('borrowerCategory', __('Choose a borrower category (set loan limits in the catalogue first).'));

            return;
        }

        try {
            app(IssueLoanAction::class)->execute(new IssueLoanData(
                termId: $term->id, copyId: $copy->id, borrowerType: $this->borrowerType, borrowerId: $this->borrowerId,
                borrowerCategory: $this->borrowerCategory, issuedByUserId: (int) auth()->id(),
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('copyCode', $exception->getMessage());

            return;
        }

        $this->reset('copyCode');
        $this->toast(__('Issued.'));
    }

    public function returnCopy(): void
    {
        $this->authorizePermission('library.circulate');
        $this->resetErrorBag();

        $copy = $this->findCopy($this->returnCode);
        $loan = $copy === null ? null : Loan::query()->where('copy_id', $copy->id)->where('status', 'active')->first();

        if ($loan === null) {
            $this->addError('returnCode', __('That copy is not on loan.'));

            return;
        }

        $componentId = $this->validComponentId();

        try {
            app(ReturnLoanAction::class)->execute(new ReturnLoanData(
                loanId: $loan->id, returnedByUserId: (int) auth()->id(), conditionAtReturn: $this->returnCondition, feeComponentId: $componentId,
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('returnCode', $exception->getMessage());

            return;
        }

        $this->reset('returnCode');
        $this->toast(__('Returned.'));
    }

    public function renew(int $loanId): void
    {
        $this->authorizePermission('library.circulate');

        $loan = Loan::query()->where('status', 'active')->find($loanId);

        if ($loan === null) {
            $this->toast(__('That loan is no longer active.'), 'danger');

            return;
        }

        try {
            app(RenewLoanAction::class)->execute(new RenewLoanData($loan->id, (int) auth()->id()));
        } catch (DomainException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Renewed.'));
    }

    public function markLost(int $loanId): void
    {
        $this->authorizePermission('library.circulate');
        $this->resetErrorBag();

        $loan = Loan::query()->where('status', 'active')->find($loanId);
        $componentId = $this->validComponentId();

        if ($loan === null || ($loan->borrower_type === 'student' && $componentId === null)) {
            $this->toast(__('Choose the fee component to charge the replacement to.'), 'danger');

            return;
        }

        try {
            app(ReportLoanLostAction::class)->execute(new ReportLoanLostData($loan->id, (int) $componentId, (int) auth()->id()));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Marked lost and charged.'));
    }

    private function validComponentId(): ?int
    {
        return $this->feeComponentId === null ? null : FeeComponent::query()->where('is_active', true)->whereKey($this->feeComponentId)->value('id');
    }

    private function findCopy(string $code): ?LibraryCopy
    {
        $code = trim($code);

        return $code === '' ? null : LibraryCopy::query()->where(fn ($q) => $q->where('accession_number', $code)->orWhere('barcode', $code))->first();
    }

    private function resolveBorrower(int $id): ?string
    {
        if ($this->borrowerType === 'staff') {
            $staff = Staff::query()->find($id);

            return $staff === null ? null : "{$staff->staff_number} — {$staff->fullName()}";
        }

        $student = Student::query()->find($id);

        return $student === null ? null : "{$student->admission_number} — {$student->fullName()}";
    }

    /**
     * @return array<int, string> borrower id => label
     */
    private function borrowerResults(): array
    {
        $term = trim($this->borrowerSearch);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        if ($this->borrowerType === 'staff') {
            return Staff::query()->where(fn ($q) => $q->where('staff_number', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like))
                ->orderBy('last_name')->limit(10)->get()->mapWithKeys(fn (Staff $s): array => [$s->id => "{$s->staff_number} — {$s->fullName()}"])->all();
        }

        return Student::query()->where(fn ($q) => $q->where('admission_number', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like))
            ->orderBy('last_name')->limit(10)->get()->mapWithKeys(fn (Student $s): array => [$s->id => "{$s->admission_number} — {$s->fullName()}"])->all();
    }

    public function render(): View
    {
        $loans = $this->borrowerId === null
            ? collect()
            : Loan::query()->with('copy.item')->where('borrower_type', $this->borrowerType)->where('borrower_id', $this->borrowerId)->where('status', 'active')->orderBy('due_on')->get();

        return view('academic::library.circulation', [
            'results' => $this->borrowerResults(),
            'loans' => $loans,
            'categories' => BorrowerCategory::query()->orderBy('category')->get(['category']),
            'components' => FeeComponent::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
