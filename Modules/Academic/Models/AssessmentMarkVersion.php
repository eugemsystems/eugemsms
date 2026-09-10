<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\AssessmentMarkVersionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Student;

/**
 * Book D ACA-05 §2/§4/BR-ACA-05-009 ⭐. Append-only, no exceptions —
 * unlike every other "mostly append-only" table in this codebase,
 * this one has no mutable column at all. A correction to a version
 * row would defeat the entire point of keeping one.
 *
 * @property int $id
 * @property int $school_id
 * @property int $assessment_id
 * @property int $student_id
 * @property int $version
 * @property string|null $raw_mark
 * @property string|null $percent
 * @property string|null $grade
 * @property string|null $change_reason
 * @property bool $was_published
 * @property int|null $approval_request_id
 * @property int $changed_by
 * @property Carbon $changed_at
 */
class AssessmentMarkVersion extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssessmentMarkVersionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'assessment_id', 'student_id', 'version', 'raw_mark', 'percent', 'grade',
        'change_reason', 'was_published', 'approval_request_id', 'changed_by', 'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'was_published' => 'boolean',
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AssessmentMarkVersionFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InvalidStateTransitionException(
                'assessment_mark_versions rows are never edited (BR-ACA-05-009).',
                [],
            );
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException(
                'assessment_mark_versions rows are never deleted (BR-ACA-05-009).',
                [],
            );
        });
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
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
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
