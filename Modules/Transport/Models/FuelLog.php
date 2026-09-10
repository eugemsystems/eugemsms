<?php

declare(strict_types=1);

namespace Modules\Transport\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\Journal;
use Modules\Stores\Models\StoreRequisition;
use Modules\Stores\Models\Supplier;
use Modules\Transport\Database\Factories\FuelLogFactory;

/**
 * Book H2 OPS-01 §2/§3 ⭐/BR-OPS-01-013/014 — see this table's own
 * migration docblock for the real `FIN-09` linkage.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $vehicle_id
 * @property Carbon $fuelled_at
 * @property float $odometer_km
 * @property float $litres
 * @property int $unit_price_minor
 * @property int $total_cost_minor
 * @property string $currency
 * @property string $source
 * @property int|null $supplier_id
 * @property int|null $store_requisition_id
 * @property int|null $driver_id
 * @property int|null $authorised_by
 * @property float|null $km_since_last
 * @property float|null $km_per_litre
 * @property float|null $variance_percent
 * @property bool $is_anomaly
 * @property int|null $anomaly_reviewed_by
 * @property string|null $anomaly_explanation
 * @property int|null $journal_id
 */
class FuelLog extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FuelLogFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'vehicle_id', 'fuelled_at', 'odometer_km', 'litres', 'unit_price_minor',
        'total_cost_minor', 'currency', 'source', 'supplier_id', 'store_requisition_id', 'receipt_file_id',
        'driver_id', 'authorised_by', 'km_since_last', 'km_per_litre', 'variance_percent', 'is_anomaly',
        'anomaly_reviewed_by', 'anomaly_explanation', 'journal_id',
    ];

    protected function casts(): array
    {
        return [
            'fuelled_at' => 'datetime',
            'odometer_km' => 'decimal:2',
            'litres' => 'decimal:2',
            'km_since_last' => 'decimal:2',
            'km_per_litre' => 'decimal:2',
            'variance_percent' => 'decimal:2',
            'is_anomaly' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FuelLogFactory::new();
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<StoreRequisition, $this>
     */
    public function storeRequisition(): BelongsTo
    {
        return $this->belongsTo(StoreRequisition::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function authorisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorised_by');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
