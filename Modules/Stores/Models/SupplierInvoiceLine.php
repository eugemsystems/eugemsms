<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Database\Factories\SupplierInvoiceLineFactory;

/**
 * @property int $id
 * @property int $school_id
 * @property int $invoice_id
 * @property int|null $po_line_id
 * @property int|null $grn_line_id
 * @property float $quantity
 * @property int $unit_price_minor
 * @property int $line_total_minor
 * @property int $expense_account_id
 */
class SupplierInvoiceLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SupplierInvoiceLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'invoice_id', 'po_line_id', 'grn_line_id', 'description', 'quantity',
        'unit_price_minor', 'tax_category', 'tax_rate_percent', 'tax_minor', 'line_total_minor',
        'expense_account_id', 'cost_centre_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'tax_rate_percent' => 'decimal:2',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SupplierInvoiceLineFactory::new();
    }

    /**
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'invoice_id');
    }

    /**
     * @return BelongsTo<PurchaseOrderLine, $this>
     */
    public function poLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'po_line_id');
    }

    /**
     * @return BelongsTo<GrnLine, $this>
     */
    public function grnLine(): BelongsTo
    {
        return $this->belongsTo(GrnLine::class, 'grn_line_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }
}
