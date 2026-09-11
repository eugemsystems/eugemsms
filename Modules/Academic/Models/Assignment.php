<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\AssignmentFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book K ACA-08 §2/BR-ACA-08-004/008 ⭐. `assessment_id` is this
 * module's own addition on top of the spec's `assessment_type_id`
 * column — see the owning migration's docblock.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $course_space_id
 * @property string $title
 * @property string $instructions
 * @property array<int, int>|null $attachment_file_ids
 * @property string|null $max_mark
 * @property int|null $rubric_id
 * @property int|null $assessment_type_id
 * @property int|null $assessment_id
 * @property Carbon $opens_at
 * @property Carbon $due_at
 * @property string $late_policy
 * @property string|null $late_penalty_percent_per_day
 * @property bool $allows_resubmission
 * @property string $submission_type
 * @property string $status
 */
class Assignment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'course_space_id', 'title', 'instructions', 'attachment_file_ids', 'max_mark',
        'rubric_id', 'assessment_type_id', 'assessment_id', 'opens_at', 'due_at', 'late_policy',
        'late_penalty_percent_per_day', 'allows_resubmission', 'submission_type', 'status',
    ];

    protected function casts(): array
    {
        return [
            'attachment_file_ids' => 'array',
            'opens_at' => 'datetime',
            'due_at' => 'datetime',
            'allows_resubmission' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AssignmentFactory::new();
    }

    /**
     * @return BelongsTo<CourseSpace, $this>
     */
    public function courseSpace(): BelongsTo
    {
        return $this->belongsTo(CourseSpace::class);
    }

    /**
     * @return BelongsTo<ProjectRubric, $this>
     */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(ProjectRubric::class, 'rubric_id');
    }

    /**
     * @return BelongsTo<AssessmentType, $this>
     */
    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return HasMany<AssignmentSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function feedsGradebook(): bool
    {
        return $this->assessment_type_id !== null;
    }
}
