<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ProjectBriefFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;

/**
 * Book E ACA-06 §2/§5/BR-ACA-06-002/004/008 — the task set to
 * learners. `status` moves draft→approved→issued→closed→archived;
 * issuing (`IssueProjectBriefAction`) is what creates every learner's
 * `LearnerProject` row.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $instrument_id
 * @property int $subject_id
 * @property int $grade_level_id
 * @property string $title
 * @property string $description
 * @property array<int, string>|null $learning_objectives
 * @property string|null $heritage_link
 * @property array<int, string> $deliverables
 * @property array<int, string>|null $resources
 * @property Carbon $starts_on
 * @property Carbon $due_on
 * @property string $max_mark
 * @property int $rubric_id
 * @property int|null $brief_document_id
 * @property string $status
 * @property int|null $approved_by
 * @property Carbon|null $issued_at
 * @property int $created_by
 */
class ProjectBrief extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ProjectBriefFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'instrument_id', 'subject_id', 'grade_level_id', 'title',
        'description', 'learning_objectives', 'heritage_link', 'deliverables', 'resources',
        'starts_on', 'due_on', 'max_mark', 'rubric_id', 'brief_document_id', 'status',
        'approved_by', 'issued_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'learning_objectives' => 'array',
            'deliverables' => 'array',
            'resources' => 'array',
            'starts_on' => 'date',
            'due_on' => 'date',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ProjectBriefFactory::new();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<AssessmentInstrument, $this>
     */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(AssessmentInstrument::class, 'instrument_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return BelongsTo<ProjectRubric, $this>
     */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(ProjectRubric::class, 'rubric_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ProjectMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class, 'brief_id')->orderBy('sequence');
    }
}
