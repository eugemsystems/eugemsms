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
use Modules\Academic\Database\Factories\LearnerProjectFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * Book E ACA-06 §2/§4/§5 — one learner's project against one brief.
 * `outcome` mirrors ACA-05's `verified`-gate pattern: only a
 * `verified` outcome counts toward the final mark
 * (BR-ACA-06-014, `ContinuousAssessmentProvider::outcomeFor()`).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $brief_id
 * @property int $student_id
 * @property int $subject_id
 * @property string|null $project_title
 * @property string $status
 * @property string|null $raw_mark
 * @property string|null $percent
 * @property string|null $grade
 * @property string|null $outcome
 * @property array<string, mixed>|null $criterion_marks
 * @property int|null $marker_staff_id
 * @property Carbon|null $marked_at
 * @property string|null $marker_comment
 * @property int|null $moderator_staff_id
 * @property Carbon|null $moderated_at
 * @property string|null $moderated_mark
 * @property string|null $moderation_note
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $exemption_reason
 * @property int $version
 * @property Carbon|null $submitted_at
 */
class LearnerProject extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LearnerProjectFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'brief_id', 'student_id', 'subject_id', 'project_title',
        'status', 'raw_mark', 'percent', 'grade', 'outcome', 'criterion_marks',
        'marker_staff_id', 'marked_at', 'marker_comment',
        'moderator_staff_id', 'moderated_at', 'moderated_mark', 'moderation_note',
        'verified_by', 'verified_at', 'exemption_reason', 'version', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'criterion_marks' => 'array',
            'marked_at' => 'datetime',
            'moderated_at' => 'datetime',
            'verified_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LearnerProjectFactory::new();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<ProjectBrief, $this>
     */
    public function brief(): BelongsTo
    {
        return $this->belongsTo(ProjectBrief::class, 'brief_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function markerStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'marker_staff_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function moderatorStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'moderator_staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return HasMany<LearnerProjectMilestone, $this>
     */
    public function milestoneSubmissions(): HasMany
    {
        return $this->hasMany(LearnerProjectMilestone::class, 'learner_project_id');
    }

    /**
     * @return HasMany<ProjectEvidence, $this>
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(ProjectEvidence::class, 'learner_project_id');
    }

    /**
     * @return HasMany<ProjectMarkVersion, $this>
     */
    public function markVersions(): HasMany
    {
        return $this->hasMany(ProjectMarkVersion::class, 'learner_project_id')->orderBy('version');
    }
}
