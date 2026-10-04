<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Reconciliation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\RunReconciliationAction;
use Modules\Finance\Domain\DataObjects\RunReconciliationData;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Models\PaymentGateway;
use Modules\Finance\Models\ReconciliationRun;

/**
 * `Finance\Reconciliation\Dashboard` (Book B FIN-05 §5 ⭐/§8,
 * `finance.reconciliation.view`). "What the gateway itself reports
 * settled" (`RunReconciliationAction`'s own `gatewaySettlements` —
 * see that DTO's docblock) has no live settlement-report API to pull
 * from yet, so a scope including `gateway` lets the bursar key in
 * rows copied from the gateway's own portal before running — the
 * honest shape of what this pass's reconciliation can actually check,
 * not a fabricated live feed. `ReconciliationRun`'s own
 * `gateway_total_minor`/`receipts_total_minor`/`bank_total_minor`/
 * `gl_total_minor`/`variance_minor` columns are never populated by
 * that action (see its own docblock on what this pass's four-way
 * check actually reaches) — this screen shows exactly what IS
 * computed (status, exception count, the exception list) and does
 * not display those four totals as if they were real figures.
 */
#[Title('Reconciliation dashboard')]
#[Layout('layouts.app')]
final class Dashboard extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $scope = 'bank';

    public ?int $gatewayId = null;

    public ?int $bankAccountId = null;

    public string $runDate = '';

    public string $currency = 'USD';

    /** @var array<int, array{reference: string, amount: string, fee: string}> */
    public array $settlementRows = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.reconciliation.view');
        $this->runDate = now()->toDateString();
        $this->currency = $this->school->base_currency ?? 'USD';
    }

    public function addSettlementRow(): void
    {
        $this->settlementRows[] = ['reference' => '', 'amount' => '', 'fee' => ''];
    }

    public function removeSettlementRow(int $index): void
    {
        unset($this->settlementRows[$index]);
        $this->settlementRows = array_values($this->settlementRows);
    }

    public function run(): void
    {
        $this->validate([
            'scope' => ['required', 'in:gateway,bank,full'],
            'runDate' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3'],
            'gatewayId' => [in_array($this->scope, ['gateway', 'full'], true) ? 'required' : 'nullable', 'integer'],
            'bankAccountId' => [in_array($this->scope, ['bank', 'full'], true) ? 'required' : 'nullable', 'integer'],
        ]);

        $settlements = [];

        foreach ($this->settlementRows as $row) {
            if ($row['reference'] === '') {
                continue;
            }

            $settlements[] = [
                'gateway_reference' => $row['reference'],
                'amount_minor' => (int) round((float) $row['amount'] * 100),
                'currency' => $this->currency,
                'fee_minor' => $row['fee'] !== '' ? (int) round((float) $row['fee'] * 100) : null,
            ];
        }

        $run = app(RunReconciliationAction::class)->execute(new RunReconciliationData(
            schoolId: $this->school->id,
            runDate: Carbon::parse($this->runDate),
            scope: $this->scope,
            currency: $this->currency,
            gatewaySettlements: $settlements,
            gatewayId: in_array($this->scope, ['gateway', 'full'], true) ? $this->gatewayId : null,
            bankAccountId: in_array($this->scope, ['bank', 'full'], true) ? $this->bankAccountId : null,
        ));

        $this->settlementRows = [];

        $this->toast($run->hasNoExceptions()
            ? __('Reconciliation clean — no exceptions.')
            : __(':count exception(s) found. Review them in the Exception workbench.', ['count' => $run->exception_count]));
    }

    public function render(): View
    {
        return view('finance::reconciliation.dashboard', [
            'gateways' => PaymentGateway::where('school_id', $this->school->id)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('school_id', $this->school->id)->where('is_active', true)->orderBy('bank_name')->get(),
            'runs' => ReconciliationRun::where('school_id', $this->school->id)->orderByDesc('ran_at')->limit(20)->get(),
        ]);
    }
}
