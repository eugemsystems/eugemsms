<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Gateways;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\PollPendingIntentAction;
use Modules\Finance\Domain\Actions\SettleGatewayPaymentAction;
use Modules\Finance\Domain\DataObjects\PollPendingIntentData;
use Modules\Finance\Domain\DataObjects\SettleGatewayPaymentData;
use Modules\Finance\Livewire\Concerns\ResolvesSystemAccounts;
use Modules\Finance\Models\PaymentIntent;

/**
 * `Finance\Gateways\Intents` (Book B FIN-05 §7/§8, `finance.gateway.view`
 * to view, `finance.gateway.force_settle` ⚠⚠ to force-settle without
 * gateway confirmation). "Poll" calls the exact same
 * `PollPendingIntentAction` the (not-yet-scheduled) decaying poll job
 * would — see that action's own docblock on the schedule itself being
 * deferred wiring, not the polling logic.
 */
#[Title('Payment intents')]
#[Layout('layouts.app')]
final class Intents extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use ResolvesSystemAccounts;
    use Toasts;

    public ?int $forceSettlingId = null;

    public string $forceGatewayReference = '';

    public string $forceAmount = '';

    public string $forceFee = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.gateway.view');
    }

    public function poll(int $intentId): void
    {
        $intent = PaymentIntent::where('school_id', $this->school->id)->findOrFail($intentId);

        app(PollPendingIntentAction::class)->execute(new PollPendingIntentData(
            paymentIntentId: $intent->id,
            processedByUserId: (int) Auth::id(),
        ));

        $this->toast(__('Polled :reference.', ['reference' => $intent->reference]));
    }

    public function openForceSettle(int $intentId): void
    {
        $this->authorizePermission('finance.gateway.force_settle');

        $intent = PaymentIntent::where('school_id', $this->school->id)->findOrFail($intentId);
        $this->forceSettlingId = $intent->id;
        $this->forceGatewayReference = (string) $intent->gateway_reference;
        $this->forceAmount = number_format($intent->amount_minor / 100, 2, '.', '');
        $this->forceFee = '';
        $this->resetErrorBag();
    }

    public function cancelForceSettle(): void
    {
        $this->forceSettlingId = null;
    }

    public function forceSettle(): void
    {
        $this->authorizePermission('finance.gateway.force_settle');

        $this->validate([
            'forceGatewayReference' => ['required', 'string', 'max:150'],
            'forceAmount' => ['required', 'numeric', 'gt:0'],
            'forceFee' => ['nullable', 'numeric', 'gte:0'],
        ]);

        try {
            app(SettleGatewayPaymentAction::class)->execute(new SettleGatewayPaymentData(
                paymentIntentId: (int) $this->forceSettlingId,
                processedByUserId: (int) Auth::id(),
                gatewayReference: $this->forceGatewayReference,
                amountMinor: (int) round((float) $this->forceAmount * 100),
                feeMinor: $this->forceFee !== '' ? (int) round((float) $this->forceFee * 100) : null,
                creditBalanceAccountId: $this->requireSystemAccount('credit_balance', __('Credit Balance')),
                suspenseAccountId: $this->requireSystemAccount('suspense', __('Suspense')),
            ));
        } catch (DomainException $e) {
            $this->addError('forceAmount', $e->getMessage());

            return;
        }

        $this->forceSettlingId = null;
        $this->toast(__('Payment force-settled.'));
    }

    public function render(): View
    {
        $query = PaymentIntent::where('school_id', $this->school->id)->with('gateway')->orderByDesc('initiated_at');

        return view('finance::gateways.intents', [
            'intents' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'reference' => ['label' => __('Reference'), 'sortable' => true, 'searchable' => true],
            'payer_name' => ['label' => __('Payer'), 'sortable' => true, 'searchable' => true],
            'amount_minor' => ['label' => __('Amount'), 'sortable' => true],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => [
                    'created' => __('Created'), 'pending' => __('Pending'), 'processing' => __('Processing'),
                    'succeeded' => __('Succeeded'), 'failed' => __('Failed'), 'cancelled' => __('Cancelled'),
                    'expired' => __('Expired'), 'refunded' => __('Refunded'),
                ],
            ],
            'initiated_at' => ['label' => __('Initiated'), 'sortable' => true],
        ];
    }
}
