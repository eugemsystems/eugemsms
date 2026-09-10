<?php

declare(strict_types=1);

namespace Modules\Transport\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Transport\Database\Factories\TripPassengerFactory;

/**
 * Book H2 OPS-01 §2 ⭐/BR-OPS-01-009/010 — the manifest itself.
 *
 * @property int $id
 * @property int $school_id
 * @property int $trip_id
 * @property int|null $student_id
 * @property int|null $staff_id
 * @property int|null $stop_id
 * @property Carbon|null $boarded_at
 * @property Carbon|null $alighted_at
 * @property string|null $boarding_method
 * @property Carbon|null $guardian_notified_at
 * @property string $status
 */
class TripPassenger extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TripPassengerFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'trip_id', 'student_id', 'staff_id', 'stop_id', 'boarded_at', 'alighted_at',
        'boarding_method', 'guardian_notified_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'boarded_at' => 'datetime',
            'alighted_at' => 'datetime',
            'guardian_notified_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TripPassengerFactory::new();
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return BelongsTo<RouteStop, $this>
     */
    public function stop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class, 'stop_id');
    }
}
