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
use Modules\Operations\Models\WorkOrder;
use Modules\Transport\Database\Factories\VehicleIncidentFactory;

/**
 * Book H2 OPS-01 §2/BR-OPS-01-017.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $vehicle_id
 * @property int|null $driver_id
 * @property int|null $trip_id
 * @property string $incident_type
 * @property Carbon $occurred_at
 * @property string $location
 * @property string $description
 * @property array<int, int>|null $learners_involved
 * @property bool $injuries
 * @property string|null $police_report_number
 * @property string|null $insurance_claim_number
 * @property int|null $estimated_damage_minor
 * @property array<int, int>|null $photo_file_ids
 * @property int|null $work_order_id
 * @property int $reported_by
 * @property string $status
 */
class VehicleIncident extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<VehicleIncidentFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'vehicle_id', 'driver_id', 'trip_id', 'incident_type', 'occurred_at', 'location',
        'description', 'learners_involved', 'injuries', 'police_report_number', 'insurance_claim_number',
        'estimated_damage_minor', 'photo_file_ids', 'work_order_id', 'reported_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'learners_involved' => 'array',
            'injuries' => 'boolean',
            'photo_file_ids' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return VehicleIncidentFactory::new();
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
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
