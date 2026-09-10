<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Database\Factories\StockTransferLineFactory;

/**
 * Book H1 FIN-09 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $transfer_id
 * @property int $item_id
 * @property float $quantity_dispatched
 * @property float|null $quantity_received
 * @property int $unit_cost_minor
 * @property int $line_cost_minor
 * @property string $currency
 */
class StockTransferLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StockTransferLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'transfer_id', 'item_id', 'quantity_dispatched', 'quantity_received',
        'unit_cost_minor', 'line_cost_minor', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'quantity_dispatched' => 'decimal:4',
            'quantity_received' => 'decimal:4',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StockTransferLineFactory::new();
    }

    /**
     * @return BelongsTo<StockTransfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'transfer_id');
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
