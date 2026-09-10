<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Database\Factories\GrnLineFactory;

/**
 * @property int $id
 * @property int $school_id
 * @property int $grn_id
 * @property int $po_line_id
 * @property int|null $item_id
 * @property float $quantity_delivered
 * @property float $quantity_accepted
 * @property float $quantity_rejected
 * @property int $unit_cost_minor
 * @property int|null $stock_lot_id
 */
class GrnLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GrnLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'grn_id', 'po_line_id', 'item_id', 'quantity_delivered', 'quantity_accepted',
        'quantity_rejected', 'rejection_reason', 'batch_number', 'expiry_date', 'unit_cost_minor',
        'stock_lot_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delivered' => 'decimal:4',
            'quantity_accepted' => 'decimal:4',
            'quantity_rejected' => 'decimal:4',
            'expiry_date' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return GrnLineFactory::new();
    }

    /**
     * @return BelongsTo<GoodsReceivedNote, $this>
     */
    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'grn_id');
    }

    /**
     * @return BelongsTo<PurchaseOrderLine, $this>
     */
    public function poLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'po_line_id');
    }

    /**
     * @return BelongsTo<StockLot, $this>
     */
    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }
}
