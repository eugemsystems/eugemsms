<?php

declare(strict_types=1);

namespace Modules\Payroll\Livewire\Loans;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Payroll\Domain\Actions\CreateStaffLoanAction;
use Modules\Payroll\Domain\DataObjects\CreateStaffLoanData;
use Modules\Payroll\Models\StaffLoan;
use Modules\People\Models\Staff;

/**
 * `Payroll\Loans\Index` (Book H3 PPL-05 §2/§6, `payroll.loan.manage`).
 * List + create, no `UpdateStaffLoanAction` exists — a loan's balance
 * only ever moves inside `PostPayrollRunAction` (never here), per
 * that action's own docblock. A `fee_offset` loan's
 * `offset_student_ids` names which learner(s) its instalment credits
 * at posting time, split evenly when more than one (BR-PPL-05-018).
 */
#[Title('Staff loans')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $staffId = null;

    public string $loanType = 'staff_loan';

    public string $principalMinor = '';

    public string $currency = 'USD';

    public string $interestRatePercent = '0';

    public string $instalmentMinor = '';

    public string $instalmentCount = '';

    public string $startsOn = '';

    public string $offsetStudentIds = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('payroll.loan.manage');

        $this->startsOn = now()->toDateString();
    }

    public function create(): void
    {
        $this->validate([
            'staffId' => ['required', 'integer'],
            'loanType' => ['required', 'in:salary_advance,staff_loan,fee_offset,equipment'],
            'principalMinor' => ['required', 'integer', 'gt:0'],
            'currency' => ['required', 'in:USD,ZWG'],
            'instalmentMinor' => ['required', 'integer', 'gt:0'],
            'instalmentCount' => ['required', 'integer', 'gt:0'],
            'startsOn' => ['required', 'date'],
        ]);

        app(CreateStaffLoanAction::class)->execute(new CreateStaffLoanData(
            schoolId: $this->school->id,
            staffId: (int) $this->staffId,
            loanType: $this->loanType,
            principalMinor: (int) $this->principalMinor,
            currency: $this->currency,
            instalmentMinor: (int) $this->instalmentMinor,
            instalmentCount: (int) $this->instalmentCount,
            startsOn: Carbon::parse($this->startsOn),
            approvedByUserId: (int) auth()->id(),
            interestRatePercent: $this->interestRatePercent,
            offsetStudentIds: $this->loanType === 'fee_offset' && $this->offsetStudentIds !== ''
                ? array_map(static fn (string $id): int => (int) trim($id), explode(',', $this->offsetStudentIds))
                : null,
        ));

        $this->reset(['principalMinor', 'instalmentMinor', 'instalmentCount', 'offsetStudentIds']);
        $this->toast(__('Loan created.'));
    }

    public function render(): View
    {
        return view('payroll::loans.index', [
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->get(),
            'loans' => StaffLoan::where('school_id', $this->school->id)->orderByDesc('id')->limit(50)->get(),
        ]);
    }
}
