<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Accounts;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CalculateSubledgerBalanceAction;
use Modules\Finance\Domain\DataObjects\CalculateSubledgerBalanceData;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\Receipt;
use Modules\People\Models\Student;

/**
 * `Finance\Accounts\LearnerAccount` (Book B FIN-03 §5, `finance.fee.view`)
 * — "the bursar's most-used screen": balance per currency, every
 * invoice, every receipt, aging. The balance is always the from-source
 * subledger figure (`CalculateSubledgerBalanceAction`, the same engine
 * FIN-04's refund gate uses), never a sum of cached invoice balances —
 * one balance definition, everywhere.
 */
#[Title('Learner account')]
#[Layout('layouts.app')]
final class LearnerAccount extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Student $student;

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.fee.view');
        $this->student = $student;
    }

    public function render(): View
    {
        $balances = [];

        foreach (Currency::cases() as $currency) {
            $balance = app(CalculateSubledgerBalanceAction::class)->execute(new CalculateSubledgerBalanceData(
                schoolId: $this->school->id,
                subledgerType: 'student',
                subledgerId: $this->student->id,
                currency: $currency->value,
                asAt: now(),
            ));

            if (! $balance->isZero()) {
                $balances[$currency->value] = $balance;
            }
        }

        return view('finance::accounts.learner-account', [
            'balances' => $balances,
            'invoices' => Invoice::where('student_id', $this->student->id)->orderByDesc('id')->get(),
            'receipts' => Receipt::where('student_id', $this->student->id)->orderByDesc('id')->get(),
        ]);
    }
}
