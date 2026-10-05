<?php

declare(strict_types=1);

namespace Modules\Payroll\Livewire\Run;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Payroll\Domain\Actions\RecordPayrollPaymentAction;
use Modules\Payroll\Domain\DataObjects\RecordPayrollPaymentData;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;

/**
 * `Payroll\Run\BankFile` (Book H3 PPL-05 §4 step 7/§6, `payroll.pay`).
 * The bank file itself is a plain CSV built from the posted run's own
 * payslips, uploaded through the real `Core\Domain\Actions\Files\
 * UploadFileAction` under the `payroll_bank_file` category
 * (registered in `CoreServiceProvider`) — matching
 * `RecordPayrollPaymentAction`'s own docblock exactly. This screen's
 * job is only that generate-then-record step; it never writes a bank
 * balance anywhere.
 */
#[Title('Payroll bank file')]
#[Layout('layouts.app')]
final class BankFile extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $selectedRunId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('payroll.pay');
    }

    public function select(int $runId): void
    {
        $this->selectedRunId = $runId;
    }

    public function generateAndRecord(): void
    {
        $this->authorizePermission('payroll.pay');

        $run = PayrollRun::findOrFail($this->selectedRunId);
        $payslips = Payslip::where('payroll_run_id', $run->id)->with('staff')->get();

        $csv = "staff_id,staff_name,bank_name,bank_branch,account_number,net_amount_minor,currency\n";

        foreach ($payslips as $payslip) {
            $staff = $payslip->staff;
            $csv .= implode(',', [
                $payslip->staff_id,
                str_replace(',', ' ', trim($staff->first_name.' '.$staff->last_name)),
                str_replace(',', ' ', (string) $staff->bank_name),
                str_replace(',', ' ', (string) $staff->bank_branch),
                (string) $staff->bank_account_number,
                $payslip->net_pay_minor,
                $payslip->currency,
            ])."\n";
        }

        $file = app(UploadFileAction::class)->execute(new UploadFileData(
            schoolId: $this->school->id,
            category: 'payroll_bank_file',
            contents: $csv,
            originalName: "payroll-bank-file-{$run->run_number}.csv",
            uploadedByUserId: (int) Auth::id(),
        ));

        try {
            app(RecordPayrollPaymentAction::class)->execute(new RecordPayrollPaymentData($run->id, $file->id));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Bank file generated and run marked paid.'));
    }

    public function render(): View
    {
        return view('payroll::run.bank-file', [
            'postedRuns' => PayrollRun::where('school_id', $this->school->id)->whereIn('status', ['posted', 'paid'])->orderByDesc('id')->get(),
        ]);
    }
}
