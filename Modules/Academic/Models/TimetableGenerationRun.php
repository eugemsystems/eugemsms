<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\TimetableGenerationRunFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book E ACA-03 §2/§4 ⭐/BR-ACA-03-005. `unplaced_requirements` is
 * how the generator stays honest — see this module's own docblocks.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $timetable_id
 * @property string $algorithm
 * @property array<string, mixed>|null $parameters
 * @property string $status
 * @property int $iterations
 * @property string|null $best_score
 * @property int|null $hard_violations
 * @property array<int, mixed>|null $soft_violation_detail
 * @property array<int, mixed>|null $unplaced_requirements
 * @property int|null $duration_seconds
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int $requested_by
 */
class TimetableGenerationRun extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TimetableGenerationRunFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'timetable_id', 'algorithm', 'parameters', 'status', 'iterations',
        'best_score', 'hard_violations', 'soft_violation_detail', 'unplaced_requirements',
        'duration_seconds', 'started_at', 'completed_at', 'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'soft_violation_detail' => 'array',
            'unplaced_requirements' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TimetableGenerationRunFactory::new();
    }

    /**
     * @return BelongsTo<Timetable, $this>
     */
    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
