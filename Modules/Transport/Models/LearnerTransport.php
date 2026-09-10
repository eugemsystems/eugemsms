<?php

declare(strict_types=1);

namespace Modules\Transport\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\LearnerFeeLine;
use Modules\People\Models\Student;
use Modules\Transport\Database\Factories\LearnerTransportFactory;

/**
 * Book H2 OPS-01 §2 ⭐/BR-OPS-01-006/007/008.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $student_id
 * @property int $route_id
 * @property int $pickup_stop_id
 * @property int|null $dropoff_stop_id
 * @property int $zone_id
 * @property string $direction
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $status
 * @property string $billing_status
 * @property int|null $fee_line_id
 * @property bool $authorised_by_guardian
 * @property string|null $notes
 */
class LearnerTransport extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LearnerTransportFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'learner_transport';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'student_id', 'route_id', 'pickup_stop_id',
        'dropoff_stop_id', 'zone_id', 'direction', 'effective_from', 'effective_to', 'status',
        'billing_status', 'fee_line_id', 'authorised_by_guardian', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'authorised_by_guardian' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LearnerTransportFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Route, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * @return BelongsTo<RouteStop, $this>
     */
    public function pickupStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class, 'pickup_stop_id');
    }

    /**
     * @return BelongsTo<RouteStop, $this>
     */
    public function dropoffStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class, 'dropoff_stop_id');
    }

    /**
     * @return BelongsTo<TransportZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(TransportZone::class, 'zone_id');
    }

    /**
     * @return BelongsTo<LearnerFeeLine, $this>
     */
    public function feeLine(): BelongsTo
    {
        return $this->belongsTo(LearnerFeeLine::class);
    }
}
