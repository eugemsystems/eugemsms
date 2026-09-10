<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Database\Factories\StockBalanceFactory;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-002 — CACHE, rebuilt from
 * `stock_movements`. Nothing writes to it directly except the rebuild.
 *
 * @property int $id
 * @property int $school_id
 * @property int $store_id
 * @property int $item_id
 * @property float $quantity_on_hand
 * @property float $quantity_committed
 * @property float $quantity_available
 * @property int $value_minor
 * @property string $currency
 * @property int|null $average_unit_cost_minor
 * @property int|null $last_movement_id
 * @property Carbon|null $last_received_on
 * @property Carbon|null $last_issued_on
 * @property Carbon|null $rebuilt_at
 */
class StockBalance extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StockBalanceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'store_id', 'item_id', 'quantity_on_hand', 'quantity_committed', 'quantity_available',
        'value_minor', 'currency', 'average_unit_cost_minor', 'last_movement_id', 'last_received_on',
        'last_issued_on', 'rebuilt_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:4',
            'quantity_committed' => 'decimal:4',
            'quantity_available' => 'decimal:4',
            'last_received_on' => 'date',
            'last_issued_on' => 'date',
            'rebuilt_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StockBalanceFactory::new();
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
