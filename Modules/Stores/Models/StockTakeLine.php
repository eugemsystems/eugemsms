<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Database\Factories\StockTakeLineFactory;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-014 ⭐ — `system_quantity` is real on
 * this row from the moment it's created (needed to compute variance
 * once a count comes in), but no query that serves the counting
 * screen ever selects it — see `GetBlindCountSheetAction`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $stock_take_id
 * @property int $item_id
 * @property float $system_quantity
 * @property float|null $counted_quantity
 * @property float|null $recount_quantity
 * @property float|null $variance_quantity
 * @property int|null $variance_value_minor
 * @property float|null $variance_percent
 * @property string|null $variance_reason
 * @property bool $requires_recount
 * @property int|null $counted_by
 * @property Carbon|null $counted_at
 */
class StockTakeLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StockTakeLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'stock_take_id', 'item_id', 'system_quantity', 'counted_quantity', 'recount_quantity',
        'variance_quantity', 'variance_value_minor', 'variance_percent', 'variance_reason',
        'requires_recount', 'counted_by', 'counted_at',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:4',
            'counted_quantity' => 'decimal:4',
            'recount_quantity' => 'decimal:4',
            'variance_quantity' => 'decimal:4',
            'requires_recount' => 'boolean',
            'counted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StockTakeLineFactory::new();
    }

    /**
     * @return BelongsTo<StockTake, $this>
     */
    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class, 'stock_take_id');
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
