<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Casts\SecondaryEncrypted;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\SafeguardingConcernFactory;

/**
 * Book G BRD-08 §2/§4 ⭐⭐/BR-BRD-08-007/009/011 — no DELETE, ever, at
 * any permission level. `reporter_user_id` is NULL for anonymous
 * reports; `description` is `SecondaryEncrypted`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $student_id
 * @property Carbon $reported_at
 * @property string $report_source
 * @property int|null $reporter_user_id
 * @property string|null $anonymous_token
 * @property string $concern_category
 * @property string $description
 * @property bool $immediate_risk
 * @property string|null $initial_action_taken
 * @property int|null $case_id
 * @property string $triage_status
 * @property int|null $triaged_by
 * @property Carbon|null $triaged_at
 * @property string|null $triage_rationale
 */
class SafeguardingConcern extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SafeguardingConcernFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'reported_at', 'report_source', 'reporter_user_id', 'anonymous_token',
        'concern_category', 'description', 'immediate_risk', 'initial_action_taken', 'case_id',
        'triage_status', 'triaged_by', 'triaged_at', 'triage_rationale',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'description' => SecondaryEncrypted::class,
            'immediate_risk' => 'boolean',
            'triaged_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new InvalidStateTransitionException('safeguarding_concerns can never be deleted at any permission level (BR-BRD-08-007).');
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SafeguardingConcernFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /**
     * @return BelongsTo<SafeguardingCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(SafeguardingCase::class, 'case_id');
    }
}
