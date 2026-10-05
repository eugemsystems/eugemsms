<?php

declare(strict_types=1);

namespace Modules\Payroll\Livewire\Returns;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Payroll\Domain\Actions\PrepareItf16ReturnAction;
use Modules\Payroll\Domain\DataObjects\PrepareItf16ReturnData;
use Modules\Payroll\Domain\Exceptions\Itf16ReconciliationException;
use Modules\Payroll\Models\StatutoryReturn;

/**
 * `Payroll\Returns\Itf16` (Book H3 PPL-05 §5/§6 🇿🇼, `payroll.returns.manage`).
 * The annual PAYE reconciliation — `PrepareItf16ReturnAction` itself
 * blocks preparation outright, naming the discrepancy, when the tax
 * year's payslip PAYE total doesn't reconcile to the twelve monthly
 * `p2_paye` returns already on file (BR-PPL-05-023, AC-PPL-05-011).
 * This screen surfaces that refusal as a toast rather than a silent
 * failure.
 */
#[Title('ITF16 annual reconciliation')]
#[Layout('layouts.app')]
final class Itf16 extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $taxYear = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('payroll.returns.manage');

        $this->taxYear = (string) now()->year;
    }

    public function prepare(): void
    {
        $this->validate(['taxYear' => ['required', 'integer']]);

        try {
            app(PrepareItf16ReturnAction::class)->execute(new PrepareItf16ReturnData(
                schoolId: $this->school->id,
                taxYear: (int) $this->taxYear,
            ));
        } catch (Itf16ReconciliationException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('ITF16 prepared and reconciled.'));
    }

    public function render(): View
    {
        return view('payroll::returns.itf16', [
            'itf16Returns' => StatutoryReturn::where('school_id', $this->school->id)->where('return_type', 'itf16_annual')->orderByDesc('period_reference')->get(),
        ]);
    }
}
