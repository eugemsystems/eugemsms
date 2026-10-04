<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Procurement\Invoices;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Domain\Actions\RegisterSupplierInvoiceAction;
use Modules\Stores\Domain\DataObjects\RegisterSupplierInvoiceData;
use Modules\Stores\Models\PurchaseOrder;
use Modules\Stores\Models\Supplier;

/**
 * `Procurement\Invoices\Register` (Book H1 FIN-08 §7 🇿🇼, `procurement.invoice.register`).
 * The non-fiscal-invoice advisory shown below the fiscal toggle is
 * computed HERE, independently, from the supplier's own
 * `is_vat_registered` flag and the lines' standard-rated tax — the
 * same real figure `RegisterSupplierInvoiceAction` itself derives and
 * stores as `input_vat_claimable`/`input_vat_minor` (BR-FIN-08-005 ⭐).
 * This is advisory only, computed again by the Action on save; it
 * never substitutes for the Action's own stored figure.
 */
#[Title('Register supplier invoice')]
#[Layout('layouts.app')]
final class Register extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $supplierId = null;

    public ?int $purchaseOrderId = null;

    public string $invoiceNumber = '';

    public string $invoiceDate;

    public string $receivedOn;

    public string $dueDate;

    public bool $isFiscalInvoice = false;

    public ?string $fiscalDeviceId = null;

    public ?string $fiscalVerificationCode = null;

    /** @var array<int, array{description: string, quantity: string, unit_price_minor: string, tax_category: string, tax_rate_percent: string, expense_account_id: string, cost_centre_id: string}> */
    public array $lines = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('procurement.invoice.register');
        $this->invoiceDate = now()->toDateString();
        $this->receivedOn = now()->toDateString();
        $this->dueDate = now()->addDays(30)->toDateString();
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = ['description' => '', 'quantity' => '1', 'unit_price_minor' => '', 'tax_category' => 'standard', 'tax_rate_percent' => '0', 'expense_account_id' => '', 'cost_centre_id' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function unclaimableVatAdvisoryMinor(): int
    {
        $supplier = $this->supplierId !== null ? Supplier::find($this->supplierId) : null;

        if ($supplier === null || ! $supplier->is_vat_registered || $this->isFiscalInvoice) {
            return 0;
        }

        $tax = 0;

        foreach ($this->lines as $line) {
            if ($line['tax_category'] === 'standard') {
                $lineTotal = (float) ($line['quantity'] ?: 0) * (int) ($line['unit_price_minor'] ?: 0);
                $tax += (int) round($lineTotal * (float) ($line['tax_rate_percent'] ?: 0) / 100);
            }
        }

        return $tax;
    }

    public function register(): void
    {
        $this->validate([
            'supplierId' => ['required', 'integer'],
            'invoiceNumber' => ['required', 'string', 'max:60'],
            'invoiceDate' => ['required', 'date'],
            'receivedOn' => ['required', 'date'],
            'dueDate' => ['required', 'date'],
            'lines' => ['array', 'min:1'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
            'lines.*.expense_account_id' => ['required', 'integer'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        try {
            $invoice = app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                supplierId: (int) $this->supplierId,
                invoiceNumber: $this->invoiceNumber,
                invoiceDate: Carbon::parse($this->invoiceDate),
                receivedOn: Carbon::parse($this->receivedOn),
                dueDate: Carbon::parse($this->dueDate),
                currency: 'USD',
                lines: collect($this->lines)->map(fn (array $l): array => [
                    'poLineId' => null,
                    'grnLineId' => null,
                    'description' => $l['description'],
                    'quantity' => (float) $l['quantity'],
                    'unitPriceMinor' => (int) $l['unit_price_minor'],
                    'taxCategory' => $l['tax_category'],
                    'taxRatePercent' => (float) $l['tax_rate_percent'],
                    'expenseAccountId' => (int) $l['expense_account_id'],
                    'costCentreId' => $l['cost_centre_id'] !== '' ? (int) $l['cost_centre_id'] : null,
                ])->all(),
                registeredByUserId: (int) auth()->id(),
                purchaseOrderId: $this->purchaseOrderId,
                isFiscalInvoice: $this->isFiscalInvoice,
                fiscalDeviceId: $this->fiscalDeviceId,
                fiscalVerificationCode: $this->fiscalVerificationCode,
            ));
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $message = $invoice->withholding_applied
            ? __('Invoice registered — withholding of :w applied (no valid tax clearance).', ['w' => number_format($invoice->withholding_minor / 100, 2)])
            : __('Invoice registered.');

        $this->reset(['invoiceNumber', 'lines', 'fiscalDeviceId', 'fiscalVerificationCode']);
        $this->addLine();
        $this->toast($message);
    }

    public function render(): View
    {
        return view('stores::procurement.invoices.register', [
            'suppliers' => Supplier::where('school_id', $this->school->id)->orderBy('name')->get(),
            'orders' => PurchaseOrder::where('school_id', $this->school->id)
                ->when($this->supplierId !== null, fn ($q) => $q->where('supplier_id', $this->supplierId))
                ->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'unclaimableVatAdvisoryMinor' => $this->unclaimableVatAdvisoryMinor(),
        ]);
    }
}
