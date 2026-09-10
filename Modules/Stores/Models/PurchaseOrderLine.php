<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Models\Account;
use Modules\Stores\Database\Factories\PurchaseOrderLineFactory;

/**
 * Book H1 FIN-08 §2/§3. `is_capital` mirrors `FIN-09`'s
 * `is_capitalisable` boundary flag — see `PurchaseOrderLine`'s own
 * migration docblock.
 *
 * @property int $id
 * @property int $school_id
 * @property int $purchase_order_id
 * @property int|null $item_id
 * @property string $description
 * @property float $quantity_ordered
 * @property float $quantity_received
 * @property float $quantity_rejected
 * @property float $quantity_invoiced
 * @property int $unit_price_minor
 * @property int|null $expense_account_id
 * @property int $line_total_minor
 * @property bool $is_capital
 * @property int|null $store_id
 */
class PurchaseOrderLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PurchaseOrderLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'purchase_order_id', 'line_number', 'item_id', 'description', 'quantity_ordered',
        'quantity_received', 'quantity_rejected', 'quantity_invoiced', 'unit', 'unit_price_minor',
        'tax_rate_percent', 'tax_category', 'line_total_minor', 'expense_account_id', 'is_capital',
        'store_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:4',
            'quantity_received' => 'decimal:4',
            'quantity_rejected' => 'decimal:4',
            'quantity_invoiced' => 'decimal:4',
            'tax_rate_percent' => 'decimal:2',
            'is_capital' => 'boolean',
        ];
    }

    public function isService(): bool
    {
        return $this->item_id === null;
    }

    public function outstandingQuantity(): float
    {
        return max(0.0, (float) $this->quantity_ordered - (float) $this->quantity_received - (float) $this->quantity_rejected);
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PurchaseOrderLineFactory::new();
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
