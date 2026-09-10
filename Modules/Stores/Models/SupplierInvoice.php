<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Finance\Models\Journal;
use Modules\Stores\Database\Factories\SupplierInvoiceFactory;

/**
 * Book H1 FIN-08 §2/§3/§4/§5 ⭐.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $supplier_id
 * @property string $invoice_number
 * @property int|null $purchase_order_id
 * @property Carbon $invoice_date
 * @property Carbon $due_date
 * @property int $total_minor
 * @property string $currency
 * @property bool $is_fiscal_invoice
 * @property bool $input_vat_claimable
 * @property int|null $input_vat_minor
 * @property bool $withholding_applied
 * @property int $withholding_minor
 * @property int $net_payable_minor
 * @property string $match_status
 * @property int|null $match_variance_minor
 * @property string $status
 * @property int $paid_minor
 * @property int $balance_minor
 * @property int|null $approved_by
 * @property int|null $journal_id
 */
class SupplierInvoice extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SupplierInvoiceFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'supplier_id', 'invoice_number', 'purchase_order_id',
        'invoice_date', 'received_on', 'due_date', 'subtotal_minor', 'tax_minor', 'total_minor', 'currency',
        'exchange_rate_id', 'base_total_minor', 'is_fiscal_invoice', 'fiscal_device_id',
        'fiscal_verification_code', 'fiscal_qr_verified', 'input_vat_claimable', 'input_vat_minor',
        'withholding_applied', 'withholding_rate_percent', 'withholding_minor', 'withholding_reason',
        'net_payable_minor', 'match_status', 'match_variance_minor', 'status', 'approval_request_id',
        'approved_by', 'paid_minor', 'balance_minor', 'journal_id', 'document_file_id', 'dispute_reason',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'received_on' => 'date',
            'due_date' => 'date',
            'is_fiscal_invoice' => 'boolean',
            'fiscal_qr_verified' => 'boolean',
            'input_vat_claimable' => 'boolean',
            'withholding_applied' => 'boolean',
            'withholding_rate_percent' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            PeriodGuard::assertWritable($model);
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SupplierInvoiceFactory::new();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return HasMany<SupplierInvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class, 'invoice_id');
    }

    /**
     * @return HasMany<SupplierPaymentAllocation, $this>
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'invoice_id');
    }
}
