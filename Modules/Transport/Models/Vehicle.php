<?php

declare(strict_types=1);

namespace Modules\Transport\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\CostCentre;
use Modules\Operations\Models\MaintenanceAsset;
use Modules\Stores\Models\FixedAsset;
use Modules\Transport\Database\Factories\VehicleFactory;

/**
 * Book H2 OPS-01 §2/BR-OPS-01-001/011/012 ⭐.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $fleet_number
 * @property string $registration_number
 * @property string $vehicle_type
 * @property int $seating_capacity
 * @property int $standing_capacity
 * @property string $fuel_type
 * @property float|null $tank_capacity_litres
 * @property float|null $expected_km_per_litre
 * @property float $current_odometer_km
 * @property int|null $fixed_asset_id
 * @property int|null $maintenance_asset_id
 * @property int $cost_centre_id
 * @property int|null $assigned_driver_id
 * @property string $status
 * @property string|null $grounded_reason
 * @property bool $is_active
 */
class Vehicle extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'fleet_number', 'registration_number', 'vehicle_type', 'make', 'model',
        'year_of_manufacture', 'chassis_number', 'engine_number', 'seating_capacity', 'standing_capacity',
        'fuel_type', 'tank_capacity_litres', 'expected_km_per_litre', 'current_odometer_km', 'fixed_asset_id',
        'maintenance_asset_id', 'cost_centre_id', 'assigned_driver_id', 'status', 'grounded_reason',
        'tracker_device_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tank_capacity_litres' => 'decimal:2',
            'expected_km_per_litre' => 'decimal:2',
            'current_odometer_km' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return VehicleFactory::new();
    }

    /**
     * @return BelongsTo<FixedAsset, $this>
     */
    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    /**
     * @return BelongsTo<MaintenanceAsset, $this>
     */
    public function maintenanceAsset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class);
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    /**
     * @return HasMany<VehicleCompliance, $this>
     */
    public function compliance(): HasMany
    {
        return $this->hasMany(VehicleCompliance::class);
    }

    /**
     * @return HasMany<FuelLog, $this>
     */
    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class);
    }
}
