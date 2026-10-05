<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Billing;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Saas\Domain\Actions\IssueTenantInvoiceAction;
use Modules\Saas\Domain\Actions\RecordTenantPaymentAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Domain\DataObjects\IssueTenantInvoiceData;
use Modules\Saas\Domain\DataObjects\RecordTenantPaymentData;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\TenantInvoice;

/**
 * `Saas\Billing\Invoices` (Book J SAA-01 §5, vendor console). The vendor's
 * own tenant billing ledger: issue the next fee invoice for a subscription
 * and record payments against it. A paid invoice takes no further payment
 * from this screen and a payment must be in the invoice's own currency.
 */
#[Title('Tenant billing')]
#[Layout('saas::layouts.vendor')]
final class Invoices extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    public string $statusFilter = '';

    public ?int $subscriptionId = null;

    public string $periodMonth = '';

    public ?int $payingInvoiceId = null;

    public string $amount = '';

    public string $method = 'bank_transfer';

    public string $reference = '';

    public function mount(): void
    {
        $this->authorizeVendor();
        $this->periodMonth = now()->format('Y-m');
    }

    public function issue(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        $this->validate([
            'subscriptionId' => ['required', 'integer'],
            'periodMonth' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $subscription = Subscription::query()->findOrFail($this->subscriptionId);
        $invoice = app(IssueTenantInvoiceAction::class)->execute(new IssueTenantInvoiceData($subscription->id, $this->periodMonth));

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'invoice.issued', "Issued {$invoice->invoice_number}", $subscription->tenant_id, ['invoice_id' => $invoice->id, 'total_minor' => $invoice->total_minor]);

        $this->toast(__('Invoice :number issued.', ['number' => $invoice->invoice_number]));
    }

    public function startPayment(int $invoiceId): void
    {
        $this->authorizeVendor();

        $invoice = TenantInvoice::query()->whereNotIn('status', ['paid', 'void'])->findOrFail($invoiceId);

        $this->payingInvoiceId = $invoice->id;
        $this->amount = number_format(max(0, $invoice->total_minor - $invoice->amountPaidMinor()) / 100, 2, '.', '');
        $this->resetErrorBag();
    }

    public function recordPayment(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        $this->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:bank_transfer,cash,card,mobile_money,gateway'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $invoice = TenantInvoice::query()->whereNotIn('status', ['paid', 'void'])->findOrFail($this->payingInvoiceId);

        try {
            $payment = app(RecordTenantPaymentAction::class)->execute(new RecordTenantPaymentData(
                invoiceId: $invoice->id, amountMinor: (int) round(((float) $this->amount) * 100), currency: $invoice->currency,
                paymentMethod: $this->method, gatewayReference: $this->reference === '' ? null : $this->reference,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'invoice.payment_recorded', "Payment on {$invoice->invoice_number}", $invoice->tenant_id, ['payment_id' => $payment->id, 'amount_minor' => $payment->amount_minor]);

        $this->payingInvoiceId = null;
        $this->toast(__('Payment recorded.'));
    }

    public function render(): View
    {
        $this->authorizeVendor();

        return view('saas::vendor.invoices', [
            'invoices' => TenantInvoice::query()->with('tenant')
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->orderByDesc('id')->limit(100)->get(),
            'subscriptions' => Subscription::query()->with('tenant')->whereNotIn('status', ['cancelled'])->orderByDesc('id')->limit(200)->get(),
        ]);
    }
}
