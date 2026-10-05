<?php

declare(strict_types=1);

namespace Modules\Payroll\Livewire\Run;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Livewire\Concerns\ResolvesSystemAccounts;
use Modules\Finance\Models\Account;
use Modules\Payroll\Domain\Actions\ApprovePayrollRunAction;
use Modules\Payroll\Domain\Actions\ComputePayrollRunAction;
use Modules\Payroll\Domain\Actions\DistributePayslipsAction;
use Modules\Payroll\Domain\Actions\PostPayrollRunAction;
use Modules\Payroll\Domain\DataObjects\ApprovePayrollRunData;
use Modules\Payroll\Domain\DataObjects\ComputePayrollRunData;
use Modules\Payroll\Domain\DataObjects\PayrollGlAccounts;
use Modules\Payroll\Domain\DataObjects\PostPayrollRunData;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;

/**
 * `Payroll\Run\Wizard` (Book H3 PPL-05 §4 ⭐/§6, `payroll.run` to
 * compute, `payroll.approve`/`payroll.post` for those two steps).
 * Folds the spec's own separate "Preview" screen into this one
 * action-bar screen (the same fold `Stores\Procurement\Orders\Index`
 * and `Stores\Assets\Depreciation\Run` already use for a lifecycle):
 * compute (`ComputePayrollRunAction` does compute+preview in one
 * call, landing the run in `preview` — never `posted` — per
 * BR-PPL-05-013), approve, then post. `ComputePayrollRunAction`
 * itself re-throws `UnconfirmedStatutoryConfigException` rather than
 * collecting it (BR-PPL-05-002) — surfaced here as a toast pointing
 * at `Statutory\Config`, never silently swallowed.
 *
 * `payroll.require_separate_approver` is enforced HERE, not in
 * `ApprovePayrollRunAction` itself — that action's own docblock
 * states permission/identity enforcement is deliberately the
 * caller's job, matching this codebase's established convention.
 *
 * The 15-account GL bundle (`PayrollGlAccounts`) resolves 14 accounts
 * by a dedicated `payroll_*` `system_key` (set once via
 * `Finance\Accounts\Editor`, the same mechanism FIN-01/06 use for
 * `rounding`/`fx_unrealised_gain`) — a bursar posting payroll monthly
 * should not re-pick fourteen accounts every run. The one exception
 * is Fee Debtors: this codebase has no single seeded system key for
 * it (the same "no system key for X specifically" gap PPL-02/PPL-03
 * already documented for income/refundable-deposits), so it is
 * picked from the accounts flagged `is_control_account` with
 * `subledger_type = 'student'` instead — a dropdown, not a free
 * chart pick, since that is exactly what distinguishes the real fee
 * debtors control account from any other.
 */
#[Title('Payroll run')]
#[Layout('layouts.app')]
final class Wizard extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use ResolvesSystemAccounts;
    use Toasts;

    public string $payDate = '';

    public string $periodStart = '';

    public string $periodEnd = '';

    public string $runType = 'regular';

    public ?int $selectedRunId = null;

    public ?int $feeDebtorsAccountId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('payroll.run');

        $this->payDate = now()->toDateString();
        $this->periodStart = now()->startOfMonth()->toDateString();
        $this->periodEnd = now()->endOfMonth()->toDateString();
    }

    public function compute(): void
    {
        $this->authorizePermission('payroll.run');

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        $this->validate([
            'payDate' => ['required', 'date'],
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date'],
            'runType' => ['required', 'in:regular,supplementary,bonus,terminal'],
        ]);

        try {
            $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                payDate: Carbon::parse($this->payDate),
                periodStart: Carbon::parse($this->periodStart),
                periodEnd: Carbon::parse($this->periodEnd),
                computedByUserId: (int) Auth::id(),
                runType: $this->runType,
            ));
        } catch (DomainException $e) {
            $this->toast(__('Blocked: :message Confirm it on Statutory configuration first.', ['message' => $e->getMessage()]), 'danger');

            return;
        }

        $this->selectedRunId = $run->id;
        $this->toast(__('Payroll run computed — :count payslip(s), :exceptions exception(s).', [
            'count' => $run->staff_count,
            'exceptions' => count((array) $run->exception_report),
        ]));
    }

    public function select(int $runId): void
    {
        $this->selectedRunId = $runId;
    }

    public function approve(): void
    {
        $this->authorizePermission('payroll.approve');

        $run = PayrollRun::findOrFail($this->selectedRunId);

        $requireSeparate = (bool) app(SettingResolver::class)->get('payroll.require_separate_approver', new ScopeChain(schoolId: $this->school->id));

        if ($requireSeparate && $run->computed_by === (int) Auth::id()) {
            $this->toast(__('A different user must approve this run from whoever computed it.'), 'danger');

            return;
        }

        try {
            app(ApprovePayrollRunAction::class)->execute(new ApprovePayrollRunData($run->id, (int) Auth::id()));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Run approved.'));
    }

    public function post(): void
    {
        $this->authorizePermission('payroll.post');

        $this->validate(['feeDebtorsAccountId' => ['required', 'integer']]);

        $glAccounts = new PayrollGlAccounts(
            salariesExpenseAccountId: $this->requireSystemAccount('payroll_salaries_expense', 'Salaries & Wages Expense'),
            employerNssaExpenseAccountId: $this->requireSystemAccount('payroll_employer_nssa_expense', 'Employer NSSA Expense'),
            employerApwcsExpenseAccountId: $this->requireSystemAccount('payroll_employer_apwcs_expense', 'Employer APWCS Expense'),
            zimdefExpenseAccountId: $this->requireSystemAccount('payroll_zimdef_expense', 'ZIMDEF Levy Expense'),
            employerNecExpenseAccountId: $this->requireSystemAccount('payroll_employer_nec_expense', 'Employer NEC Expense'),
            netSalariesPayableAccountId: $this->requireSystemAccount('payroll_net_salaries_payable', 'Net Salaries Payable'),
            payePayableAccountId: $this->requireSystemAccount('payroll_paye_payable', 'PAYE Payable'),
            aidsLevyPayableAccountId: $this->requireSystemAccount('payroll_aids_levy_payable', 'AIDS Levy Payable'),
            nssaPayableAccountId: $this->requireSystemAccount('payroll_nssa_payable', 'NSSA Payable'),
            apwcsPayableAccountId: $this->requireSystemAccount('payroll_apwcs_payable', 'APWCS Payable'),
            zimdefPayableAccountId: $this->requireSystemAccount('payroll_zimdef_payable', 'ZIMDEF Payable'),
            necPayableAccountId: $this->requireSystemAccount('payroll_nec_payable', 'NEC Payable'),
            staffLoansReceivableAccountId: $this->requireSystemAccount('payroll_staff_loans_receivable', 'Staff Loans Receivable'),
            feeDebtorsAccountId: (int) $this->feeDebtorsAccountId,
            thirdPartyPayablesAccountId: $this->requireSystemAccount('payroll_third_party_payables', 'Third-Party Payables'),
        );

        try {
            app(PostPayrollRunAction::class)->execute(new PostPayrollRunData($this->selectedRunId, $glAccounts, (int) Auth::id()));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Run posted — statutory returns prepared.'));
    }

    public function distribute(): void
    {
        $this->authorizePermission('payroll.post');

        try {
            app(DistributePayslipsAction::class)->execute((int) $this->selectedRunId);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Payslips distributed to staff self-service.'));
    }

    public function render(): View
    {
        $selectedRun = $this->selectedRunId !== null ? PayrollRun::find($this->selectedRunId) : null;

        return view('payroll::run.wizard', [
            'runs' => PayrollRun::where('school_id', $this->school->id)->orderByDesc('id')->limit(30)->get(),
            'selectedRun' => $selectedRun,
            'payslips' => $selectedRun !== null ? Payslip::where('payroll_run_id', $selectedRun->id)->with('staff')->get() : collect(),
            'feeDebtorsAccounts' => Account::where('school_id', $this->school->id)
                ->where('is_control_account', true)->where('subledger_type', 'student')->get(),
        ]);
    }
}
