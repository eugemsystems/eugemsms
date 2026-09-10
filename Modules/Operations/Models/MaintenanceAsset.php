<?php

declare(strict_types=1);

namespace Modules\Operations\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\CostCentre;
use Modules\Operations\Database\Factories\MaintenanceAssetFactory;
use Modules\Stores\Models\FixedAsset;

/**
 * Book H2 OPS-02 §2 — wider than `FIN-10`'s own asset register; a
 * building or borehole is maintainable without ever being
 * capitalised.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $asset_type
 * @property int|null $fixed_asset_id
 * @property int|null $vehicle_id
 * @property string|null $location
 * @property int $cost_centre_id
 * @property string $criticality
 * @property string $condition
 * @property int|null $service_interval_days
 * @property float|null $service_interval_units
 * @property Carbon|null $last_serviced_on
 * @property float|null $last_service_units
 * @property Carbon|null $next_service_due_on
 * @property float|null $next_service_due_units
 * @property bool $is_active
 */
class MaintenanceAsset extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MaintenanceAssetFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'asset_type', 'fixed_asset_id', 'vehicle_id', 'location', 'building',
        'cost_centre_id', 'criticality', 'condition', 'commissioned_on', 'warranty_expires_on',
        'service_interval_days', 'service_interval_units', 'last_serviced_on', 'last_service_units',
        'next_service_due_on', 'next_service_due_units', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'commissioned_on' => 'date',
            'warranty_expires_on' => 'date',
            'service_interval_units' => 'decimal:2',
            'last_serviced_on' => 'date',
            'last_service_units' => 'decimal:2',
            'next_service_due_on' => 'date',
            'next_service_due_units' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function isServiceOverdue(): bool
    {
        if ($this->next_service_due_on !== null && $this->next_service_due_on->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MaintenanceAssetFactory::new();
    }

    /**
     * @return BelongsTo<FixedAsset, $this>
     */
    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return HasMany<FaultReport, $this>
     */
    public function faultReports(): HasMany
    {
        return $this->hasMany(FaultReport::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * @return HasMany<MaintenanceSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }
}
