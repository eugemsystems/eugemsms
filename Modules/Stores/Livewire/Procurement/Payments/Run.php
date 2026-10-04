<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Procurement\Payments;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\BankAccount;
use Modules\Stores\Domain\Actions\RecordSupplierPaymentAction;
use Modules\Stores\Domain\DataObjects\RecordSupplierPaymentData;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierInvoice;

/**
 * `Procurement\Payments\Run` (Book H1 FIN-08 §7, `procurement.payment.create` ⚠⚠,
 * AC-FIN-08-008). The approver here must differ from whoever approved
 * ANY selected invoice — `RecordSupplierPaymentAction` enforces this
 * per invoice and this screen surfaces its refusal; no client-side
 * pre-check duplicates that logic.
 */
#[Title('Payment run')]
#[Layout('layouts.app')]
final class Run extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $supplierId = null;

    /** @var array<int, int> */
    public array $invoiceIds = [];

    public string $paymentDate;

    public string $paymentMethod = 'bank_transfer';

    public ?int $bankAccountId = null;

    public ?int $withholdingPayableAccountId = null;

    public ?string $reference = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('procurement.payment.create');
        $this->paymentDate = now()->toDateString();
    }

    public function pay(): void
    {
        $this->validate([
            'supplierId' => ['required', 'integer'],
            'invoiceIds' => ['array', 'min:1'],
            'paymentDate' => ['required', 'date'],
            'bankAccountId' => ['required', 'integer'],
        ]);

        $termId = SessionContext::termId();

        if ($termId === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        try {
            $payment = app(RecordSupplierPaymentAction::class)->execute(new RecordSupplierPaymentData(
                schoolId: $this->school->id,
                termId: $termId,
                supplierId: (int) $this->supplierId,
                invoiceIds: array_map('intval', $this->invoiceIds),
                paymentDate: Carbon::parse($this->paymentDate),
                paymentMethod: $this->paymentMethod,
                bankAccountId: (int) $this->bankAccountId,
                approvedByUserId: (int) auth()->id(),
                withholdingPayableAccountId: $this->withholdingPayableAccountId,
                reference: $this->reference,
            ));
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['invoiceIds', 'reference']);
        $this->toast(__('Payment :n recorded — net :amount.', ['n' => $payment->payment_number, 'amount' => number_format($payment->net_minor / 100, 2)]));
    }

    public function render(): View
    {
        return view('stores::procurement.payments.run', [
            'suppliers' => Supplier::where('school_id', $this->school->id)->orderBy('name')->get(),
            'dueInvoices' => $this->supplierId !== null
                ? SupplierInvoice::where('supplier_id', $this->supplierId)->whereIn('status', ['approved', 'partially_paid'])->get()
                : collect(),
            'bankAccounts' => BankAccount::where('school_id', $this->school->id)->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
