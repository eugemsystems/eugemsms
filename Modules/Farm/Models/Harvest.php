<?php

declare(strict_types=1);

namespace Modules\Farm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Farm\Database\Factories\HarvestFactory;
use Modules\Finance\Models\Journal;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\Store;

/**
 * Book H2 OPS-03 §2/§3 ⭐/BR-OPS-03-006 — real `FIN-09` lot.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $crop_cycle_id
 * @property Carbon $harvested_on
 * @property float $quantity_kg
 * @property string|null $quality_grade
 * @property float|null $moisture_percent
 * @property int $unit_cost_minor
 * @property string $currency
 * @property string $destination
 * @property int|null $store_id
 * @property int|null $stock_lot_id
 * @property int|null $journal_id
 * @property int $recorded_by
 */
class Harvest extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HarvestFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'crop_cycle_id', 'harvested_on', 'quantity_kg', 'quality_grade', 'moisture_percent',
        'unit_cost_minor', 'currency', 'destination', 'store_id', 'stock_lot_id', 'journal_id', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'harvested_on' => 'date',
            'quantity_kg' => 'decimal:2',
            'moisture_percent' => 'decimal:2',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HarvestFactory::new();
    }

    /**
     * @return BelongsTo<CropCycle, $this>
     */
    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(CropCycle::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<StockLot, $this>
     */
    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
