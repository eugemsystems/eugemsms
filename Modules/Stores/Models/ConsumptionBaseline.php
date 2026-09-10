<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Database\Factories\ConsumptionBaselineFactory;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-022 ⭐ — normalised per boarder-day
 * where the store serves boarding.
 *
 * @property int $id
 * @property int $school_id
 * @property int $store_id
 * @property int $item_id
 * @property string $period_type
 * @property float $expected_quantity
 * @property float $tolerance_percent
 * @property int $computed_from_days
 * @property Carbon|null $last_computed_at
 */
class ConsumptionBaseline extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ConsumptionBaselineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'store_id', 'item_id', 'period_type', 'expected_quantity',
        'tolerance_percent', 'computed_from_days', 'last_computed_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:4',
            'tolerance_percent' => 'decimal:2',
            'last_computed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ConsumptionBaselineFactory::new();
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
