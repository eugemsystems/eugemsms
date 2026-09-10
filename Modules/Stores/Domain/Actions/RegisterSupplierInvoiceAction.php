<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Stores\Domain\DataObjects\RegisterSupplierInvoiceData;
use Modules\Stores\Domain\Events\DuplicateInvoiceSuspected;
use Modules\Stores\Domain\Events\MatchVarianceDetected;
use Modules\Stores\Domain\Events\NonFiscalInvoiceRegistered;
use Modules\Stores\Domain\Events\WithholdingApplied;
use Modules\Stores\Models\GrnLine;
use Modules\Stores\Models\PurchaseOrderLine;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierInvoice;
use Modules\Stores\Models\SupplierInvoiceLine;
use Modules\Stores\Models\SupplierTaxClearance;

/**
 * ACT-RegisterSupplierInvoice (Book H1 FIN-08 §6 ⭐⭐/§3/§4/§5/
 * BR-FIN-08-003/005/016/017/018/AC-FIN-08-001/002/003/005/010). Every
 * concern in this action is evaluated once, at registration, and
 * stored — never recomputed silently later: withholding, VAT
 * claimability, and the three-way match all read as historical facts
 * about this specific invoice from here on.
 */
final class RegisterSupplierInvoiceAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(RegisterSupplierInvoiceData $data): SupplierInvoice
    {
        $supplier = Supplier::findOrFail($data->supplierId);
        $scope = new ScopeChain(schoolId: $data->schoolId);

        $duplicateReason = $this->checkForDuplicate($data);

        $subtotal = 0;
        $tax = 0;
        $standardRatedTax = 0;

        foreach ($data->lines as $line) {
            $lineTotal = (int) round($line['quantity'] * $line['unitPriceMinor']);
            $lineTax = (int) round($lineTotal * $line['taxRatePercent'] / 100);
            $subtotal += $lineTotal;
            $tax += $lineTax;

            if ($line['taxCategory'] === 'standard') {
                $standardRatedTax += $lineTax;
            }
        }

        $total = $subtotal + $tax;

        // BR-FIN-08-003 ⭐ — validity is assessed on the INVOICE date.
        $hasValidClearance = SupplierTaxClearance::where('supplier_id', $supplier->id)
            ->get()
            ->contains(fn (SupplierTaxClearance $c): bool => $c->isValidOn($data->invoiceDate));

        $withholdingRate = (float) $this->settings->get('procurement.withholding_rate_percent', $scope);
        $withholdingApplied = ! $hasValidClearance;
        $withholdingMinor = $withholdingApplied ? (int) round($total * $withholdingRate / 100) : 0;
        $netPayable = $total - $withholdingMinor;

        // BR-FIN-08-005 ⭐ — input VAT claimable only when fiscal AND VAT-registered.
        $inputVatClaimable = $supplier->is_vat_registered && $data->isFiscalInvoice && $standardRatedTax > 0;
        $inputVatMinor = $inputVatClaimable ? $standardRatedTax : null;

        [$matchStatus, $matchVarianceMinor] = $this->matchLines($data, $scope);

        return $this->transaction(function () use ($data, $supplier, $duplicateReason, $subtotal, $tax, $total, $withholdingApplied, $withholdingRate, $withholdingMinor, $netPayable, $inputVatClaimable, $inputVatMinor, $standardRatedTax, $matchStatus, $matchVarianceMinor): SupplierInvoice {
            $invoice = SupplierInvoice::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'supplier_id' => $supplier->id,
                'invoice_number' => $data->invoiceNumber,
                'purchase_order_id' => $data->purchaseOrderId,
                'invoice_date' => $data->invoiceDate->toDateString(),
                'received_on' => $data->receivedOn->toDateString(),
                'due_date' => $data->dueDate->toDateString(),
                'subtotal_minor' => $subtotal,
                'tax_minor' => $tax,
                'total_minor' => $total,
                'currency' => $data->currency,
                'base_total_minor' => $total,
                'is_fiscal_invoice' => $data->isFiscalInvoice,
                'fiscal_device_id' => $data->fiscalDeviceId,
                'fiscal_verification_code' => $data->fiscalVerificationCode,
                'input_vat_claimable' => $inputVatClaimable,
                'input_vat_minor' => $inputVatMinor,
                'withholding_applied' => $withholdingApplied,
                'withholding_rate_percent' => $withholdingApplied ? $withholdingRate : null,
                'withholding_minor' => $withholdingMinor,
                'withholding_reason' => $withholdingApplied ? 'no_valid_itf263' : null,
                'net_payable_minor' => $netPayable,
                'match_status' => $matchStatus,
                'match_variance_minor' => $matchVarianceMinor,
                'status' => 'received',
                'paid_minor' => 0,
                'balance_minor' => $netPayable,
            ]);

            foreach ($data->lines as $line) {
                $lineTotal = (int) round($line['quantity'] * $line['unitPriceMinor']);
                $lineTax = (int) round($lineTotal * $line['taxRatePercent'] / 100);

                SupplierInvoiceLine::create([
                    'school_id' => $data->schoolId,
                    'invoice_id' => $invoice->id,
                    'po_line_id' => $line['poLineId'],
                    'grn_line_id' => $line['grnLineId'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price_minor' => $line['unitPriceMinor'],
                    'tax_category' => $line['taxCategory'],
                    'tax_rate_percent' => $line['taxRatePercent'],
                    'tax_minor' => $lineTax,
                    'line_total_minor' => $lineTotal + $lineTax,
                    'expense_account_id' => $line['expenseAccountId'],
                    'cost_centre_id' => $line['costCentreId'],
                ]);

                if ($line['poLineId'] !== null) {
                    PurchaseOrderLine::where('id', $line['poLineId'])->increment('quantity_invoiced', $line['quantity']);
                }
            }

            if ($withholdingApplied) {
                event(new WithholdingApplied($invoice));
            }

            if ($supplier->is_vat_registered && ! $data->isFiscalInvoice && $standardRatedTax > 0) {
                event(new NonFiscalInvoiceRegistered($invoice, $standardRatedTax));
            }

            if ($matchStatus === 'variance') {
                event(new MatchVarianceDetected($invoice));
            }

            if ($duplicateReason !== null) {
                event(new DuplicateInvoiceSuspected($invoice, $duplicateReason));
            }

            return $invoice;
        });
    }

    private function checkForDuplicate(RegisterSupplierInvoiceData $data): ?string
    {
        // The exact-match case (same supplier + invoice number) is always
        // refused, `acknowledgeDuplicate` or not — `supplier_invoices`
        // carries a hard UNIQUE(school_id, supplier_id, invoice_number)
        // constraint, so a second row under the identical number can
        // never actually be stored. A genuine reissue arrives from the
        // supplier under its own new number; this is not something an
        // "acknowledge and proceed" flag can override.
        $exact = SupplierInvoice::where('supplier_id', $data->supplierId)
            ->where('invoice_number', $data->invoiceNumber)
            ->exists();

        if ($exact) {
            throw ValidationException::withMessages([
                'invoiceNumber' => "Invoice {$data->invoiceNumber} already exists for this supplier (BR-FIN-08-018).",
            ]);
        }

        $total = 0;

        foreach ($data->lines as $line) {
            $lineTotal = (int) round($line['quantity'] * $line['unitPriceMinor']);
            $total += $lineTotal + (int) round($lineTotal * $line['taxRatePercent'] / 100);
        }

        $near = SupplierInvoice::where('supplier_id', $data->supplierId)
            ->where('total_minor', $total)
            ->whereBetween('invoice_date', [$data->invoiceDate->copy()->subDays(3), $data->invoiceDate->copy()->addDays(3)])
            ->exists();

        return $near ? 'Same supplier, amount and a nearby invoice date already exist — possible duplicate.' : null;
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function matchLines(RegisterSupplierInvoiceData $data, ScopeChain $scope): array
    {
        $quantityTolerancePercent = (float) $this->settings->get('procurement.match_quantity_tolerance_percent', $scope);
        $priceTolerancePercent = (float) $this->settings->get('procurement.match_price_tolerance_percent', $scope);

        $hasPoLines = false;
        $unmatched = false;
        $variance = false;
        $varianceMinor = 0;

        foreach ($data->lines as $line) {
            if ($line['poLineId'] === null) {
                continue;
            }

            $hasPoLines = true;
            $poLine = PurchaseOrderLine::find($line['poLineId']);

            if ($poLine === null || $poLine->isService()) {
                continue;
            }

            $grnLine = $line['grnLineId'] !== null
                ? GrnLine::find($line['grnLineId'])
                : GrnLine::where('po_line_id', $poLine->id)->where('quantity_accepted', '>', 0)->first();

            if ($grnLine === null) {
                $unmatched = true;

                continue;
            }

            $quantityDiff = abs($line['quantity'] - (float) $grnLine->quantity_accepted);
            $quantityTolerance = max((float) $grnLine->quantity_accepted * $quantityTolerancePercent / 100, 1.0);

            $priceDiffPercent = $poLine->unit_price_minor > 0
                ? abs($line['unitPriceMinor'] - $poLine->unit_price_minor) / $poLine->unit_price_minor * 100
                : 0.0;

            if ($quantityDiff > $quantityTolerance || $priceDiffPercent > $priceTolerancePercent) {
                $variance = true;
                $varianceMinor += (int) round(($line['unitPriceMinor'] - $poLine->unit_price_minor) * $line['quantity']);
            }
        }

        // No line references a purchase order at all — a standalone bill
        // (e.g. a utility invoice) sits entirely outside three-way
        // matching, so there is nothing to block approval on.
        if (! $hasPoLines) {
            return ['matched', null];
        }

        if ($unmatched) {
            return ['unmatched', null];
        }

        if ($variance) {
            return ['variance', $varianceMinor];
        }

        return ['matched', null];
    }
}
