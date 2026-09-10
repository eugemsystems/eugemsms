<?php

declare(strict_types=1);

namespace Modules\Transport\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Transport\Database\Factories\RouteStopFactory;

/**
 * Book H2 OPS-01 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $route_id
 * @property int $sequence
 * @property string $name
 * @property string|null $landmark
 * @property float|null $latitude
 * @property float|null $longitude
 * @property float|null $distance_from_school_km
 * @property int|null $zone_id
 * @property string|null $scheduled_time
 */
class RouteStop extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<RouteStopFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'route_id', 'sequence', 'name', 'landmark', 'latitude', 'longitude',
        'distance_from_school_km', 'zone_id', 'scheduled_time',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'distance_from_school_km' => 'decimal:2',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RouteStopFactory::new();
    }

    /**
     * @return BelongsTo<Route, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * @return BelongsTo<TransportZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(TransportZone::class, 'zone_id');
    }
}
