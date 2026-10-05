<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Messaging\Reports;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\ReconcileGatewayCostAction;
use Modules\Comms\Domain\DataObjects\ReconcileGatewayCostData;
use Modules\Comms\Models\GatewayCostReconciliation;
use Modules\Comms\Models\MessageGateway;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Reports\Reconciliation` (Book I COM-01 §6 ⭐,
 * `comms.reconciliation.manage`). The provider's invoiced total is
 * entered as a decimal string and converted with `Money::fromDecimal`
 * — never a float. `gateway_cost_reconciliation` carries no currency
 * column, so amounts are read and entered in the school's base
 * currency. A variance beyond tolerance is flagged, not absorbed
 * (AC-COM-01-006) — the Action decides that, this screen only shows it.
 */
#[Title('Cost reconciliation')]
#[Layout('layouts.app')]
final class Reconciliation extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $gatewayId = null;

    public string $periodMonth = '';

    public string $providerInvoiced = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('comms.reconciliation.manage');

        $this->periodMonth = now()->subMonth()->format('Y-m');
    }

    public function reconcile(): void
    {
        $this->authorizePermission('comms.reconciliation.manage');

        $this->validate([
            'gatewayId' => ['required', 'integer'],
            'periodMonth' => ['required', 'date_format:Y-m'],
            'providerInvoiced' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
        ], [
            'providerInvoiced.regex' => __('Enter an amount like 460 or 460.50.'),
        ]);

        $gateway = MessageGateway::where('school_id', $this->school->id)->findOrFail($this->gatewayId);

        try {
            $invoicedMinor = $this->providerInvoiced !== ''
                ? Money::fromDecimal($this->providerInvoiced, $this->currency())->minor
                : null;
        } catch (InvalidArgumentException) {
            $this->addError('providerInvoiced', __('Enter a valid amount.'));

            return;
        }

        $reconciliation = app(ReconcileGatewayCostAction::class)->execute(new ReconcileGatewayCostData(
            schoolId: $this->school->id,
            gatewayId: $gateway->id,
            periodMonth: $this->periodMonth,
            providerInvoicedMinor: $invoicedMinor,
        ));

        $this->reset(['providerInvoiced']);
        $this->toast(
            $reconciliation->status === 'variance' ? __('Variance beyond tolerance — flagged for investigation.') : __('Reconciliation recorded.'),
            $reconciliation->status === 'variance' ? 'warning' : 'success',
        );
    }

    public function render(): View
    {
        return view('comms::reports.reconciliation', [
            'gateways' => MessageGateway::where('school_id', $this->school->id)->orderBy('name')->get(['id', 'name', 'channel']),
            'reconciliations' => GatewayCostReconciliation::with('gateway:id,name')->where('school_id', $this->school->id)->orderByDesc('period_month')->orderByDesc('id')->limit(100)->get(),
            'currency' => $this->currency(),
        ]);
    }

    private function currency(): Currency
    {
        return Currency::from($this->school->base_currency);
    }
}
