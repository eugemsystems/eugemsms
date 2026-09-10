<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Stores\Database\Factories\StockLotFactory;

/**
 * Book H1 FIN-09 §2/§4 ⭐ — a FIFO layer. `quantity_remaining` is only
 * ever decremented by `IssueStockAction` under a row lock
 * (`lockForUpdate()`), never edited directly by any other path.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $store_id
 * @property int $item_id
 * @property string $lot_reference
 * @property string|null $batch_number
 * @property Carbon $received_on
 * @property Carbon|null $expiry_date
 * @property float $quantity_received
 * @property float $quantity_remaining
 * @property int $unit_cost_minor
 * @property string $currency
 * @property int $base_unit_cost_minor
 * @property int|null $exchange_rate_id
 * @property string $source_type
 * @property int|null $source_id
 * @property int|null $supplier_id
 * @property bool $is_depleted
 */
class StockLot extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StockLotFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'store_id', 'item_id', 'lot_reference', 'batch_number', 'received_on', 'expiry_date',
        'quantity_received', 'quantity_remaining', 'unit_cost_minor', 'currency', 'base_unit_cost_minor',
        'exchange_rate_id', 'source_type', 'source_id', 'supplier_id', 'is_depleted',
    ];

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'expiry_date' => 'date',
            'quantity_received' => 'decimal:4',
            'quantity_remaining' => 'decimal:4',
            'is_depleted' => 'boolean',
        ];
    }

    /**
     * BR-FIN-09-011 — expired stock cannot be issued.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->toDateString() < now()->toDateString();
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StockLotFactory::new();
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
