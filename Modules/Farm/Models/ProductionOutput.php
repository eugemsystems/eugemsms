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
use Modules\Farm\Database\Factories\ProductionOutputFactory;
use Modules\Finance\Models\Journal;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\Store;

/**
 * Book H2 OPS-03 §2/BR-OPS-03-015.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $production_unit_id
 * @property Carbon $output_date
 * @property string $output_type
 * @property float $quantity
 * @property string $unit
 * @property int|null $unit_cost_minor
 * @property string $currency
 * @property string $destination
 * @property int|null $store_id
 * @property int|null $stock_lot_id
 * @property int|null $journal_id
 * @property int $recorded_by
 */
class ProductionOutput extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ProductionOutputFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'production_unit_id', 'output_date', 'output_type', 'quantity', 'unit',
        'unit_cost_minor', 'currency', 'destination', 'store_id', 'stock_lot_id', 'journal_id', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'output_date' => 'date',
            'quantity' => 'decimal:3',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ProductionOutputFactory::new();
    }

    /**
     * @return BelongsTo<ProductionUnit, $this>
     */
    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
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
