<?php

declare(strict_types=1);

namespace Modules\Transport\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Staff;
use Modules\Transport\Database\Factories\TripFactory;

/**
 * Book H2 OPS-01 §2 ⭐/BR-OPS-01-009/011.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property Carbon $trip_date
 * @property string $trip_type
 * @property int|null $route_id
 * @property int $vehicle_id
 * @property int $driver_id
 * @property int|null $escort_staff_id
 * @property string|null $purpose
 * @property string|null $destination
 * @property float|null $departure_odometer
 * @property float|null $return_odometer
 * @property float|null $distance_km
 * @property Carbon|null $departed_at
 * @property Carbon|null $returned_at
 * @property int|null $passenger_count
 * @property string $status
 * @property string|null $source_type
 * @property int|null $source_id
 */
class Trip extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TripFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'trip_date', 'trip_type', 'route_id', 'vehicle_id', 'driver_id',
        'escort_staff_id', 'purpose', 'destination', 'departure_odometer', 'return_odometer', 'distance_km',
        'departed_at', 'returned_at', 'passenger_count', 'status', 'source_type', 'source_id',
        'manifest_document_id',
    ];

    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
            'departure_odometer' => 'decimal:2',
            'return_odometer' => 'decimal:2',
            'distance_km' => 'decimal:2',
            'departed_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TripFactory::new();
    }

    /**
     * @return BelongsTo<Route, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function escortStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'escort_staff_id');
    }

    /**
     * @return HasMany<TripPassenger, $this>
     */
    public function passengers(): HasMany
    {
        return $this->hasMany(TripPassenger::class);
    }
}
