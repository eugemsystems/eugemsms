<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\CbtCandidateAttemptFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Student;

/**
 * Book K ACA-09 §2/§3 ⭐/BR-ACA-09-002/004. `seeded_question_order` is
 * fixed at start and never reshuffled on resume; remaining time is
 * always computed server-side from `started_at`/the test's own
 * `duration_minutes`/`extra_time_minutes` — see `remainingSeconds()`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $test_id
 * @property int $student_id
 * @property array<int, int> $seeded_question_order
 * @property Carbon|null $started_at
 * @property Carbon|null $last_autosave_at
 * @property int $extra_time_minutes
 * @property Carbon|null $submitted_at
 * @property bool $auto_submitted
 * @property int $tab_switch_count
 * @property array<int, array<string, mixed>>|null $focus_events
 * @property string|null $raw_mark
 * @property string|null $percent
 * @property string $status
 */
class CbtCandidateAttempt extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CbtCandidateAttemptFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'test_id', 'student_id', 'seeded_question_order', 'started_at', 'last_autosave_at',
        'extra_time_minutes', 'submitted_at', 'auto_submitted', 'tab_switch_count', 'focus_events',
        'raw_mark', 'percent', 'status',
    ];

    protected function casts(): array
    {
        return [
            'seeded_question_order' => 'array',
            'started_at' => 'datetime',
            'last_autosave_at' => 'datetime',
            'extra_time_minutes' => 'integer',
            'submitted_at' => 'datetime',
            'auto_submitted' => 'boolean',
            'tab_switch_count' => 'integer',
            'focus_events' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CbtCandidateAttemptFactory::new();
    }

    /**
     * @return BelongsTo<CbtTest, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(CbtTest::class, 'test_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<CbtResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(CbtResponse::class, 'attempt_id');
    }

    /**
     * Book K ACA-09 §3 ⭐/BR-ACA-09-002. Never trust a client clock —
     * this is the one and only place "how long is left" is decided.
     */
    public function remainingSeconds(): int
    {
        if ($this->started_at === null) {
            return $this->test->duration_minutes * 60;
        }

        $deadline = $this->started_at->copy()->addMinutes($this->test->duration_minutes + $this->extra_time_minutes);
        $now = Carbon::now();

        if ($now->greaterThanOrEqualTo($deadline)) {
            return 0;
        }

        return (int) $now->diffInSeconds($deadline);
    }
}
