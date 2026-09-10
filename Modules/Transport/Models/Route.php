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
use Modules\People\Models\Staff;
use Modules\Transport\Database\Factories\RouteFactory;

/**
 * Book H2 OPS-01 §2/BR-OPS-01-007.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property string $code
 * @property string $name
 * @property string $direction
 * @property int|null $assigned_vehicle_id
 * @property int|null $assigned_driver_id
 * @property int|null $assistant_staff_id
 * @property float|null $total_distance_km
 * @property int|null $estimated_duration_min
 * @property string|null $departure_time
 * @property int $capacity
 * @property int $current_passengers
 * @property int $cost_centre_id
 * @property bool $is_active
 */
class Route extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<RouteFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'code', 'name', 'direction', 'assigned_vehicle_id',
        'assigned_driver_id', 'assistant_staff_id', 'total_distance_km', 'estimated_duration_min',
        'departure_time', 'capacity', 'current_passengers', 'cost_centre_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_distance_km' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RouteFactory::new();
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function assignedVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'assigned_vehicle_id');
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function assistantStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assistant_staff_id');
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return HasMany<RouteStop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class);
    }
}
