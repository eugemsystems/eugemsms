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
use Modules\Academic\Database\Factories\AssessmentFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;

/**
 * Book D ACA-05 §2 — a concrete assessable event. `status` moves
 * draft→open→submitted→moderated→approved→published→locked;
 * `SubmitAssessmentMarksAction` is the only sanctioned way past
 * `submitted` (BR-ACA-05-006 — marks are visible beyond the entering
 * teacher only once submitted).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $assessment_type_id
 * @property int $subject_id
 * @property int|null $grade_level_id
 * @property int|null $class_id
 * @property int|null $teaching_group_id
 * @property string $title
 * @property string $max_mark
 * @property string $weight_percent
 * @property Carbon|null $assessed_on
 * @property int|null $grading_scale_id
 * @property string $status
 * @property int $created_by
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $published_at
 */
class Assessment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'assessment_type_id', 'subject_id',
        'grade_level_id', 'class_id', 'teaching_group_id', 'title', 'max_mark', 'weight_percent',
        'assessed_on', 'grading_scale_id', 'status', 'created_by', 'submitted_by', 'submitted_at',
        'approved_by', 'approved_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'assessed_on' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AssessmentFactory::new();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<AssessmentType, $this>
     */
    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
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
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<GradingScale, $this>
     */
    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AssessmentMark, $this>
     */
    public function marks(): HasMany
    {
        return $this->hasMany(AssessmentMark::class);
    }
}
