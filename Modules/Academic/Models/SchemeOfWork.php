<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Database\Factories\SchemeOfWorkFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\File;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * Book K ACA-11 §2/BR-ACA-11-001 — one per (term, subject, grade
 * level, teacher). Table is `schemes_of_work` (plural on the first
 * word, per spec) — `protected $table` is set explicitly since
 * Eloquent's default guess would pluralize the last word instead
 * (`scheme_of_works`).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $subject_id
 * @property int $grade_level_id
 * @property int $teacher_staff_id
 * @property array<int, array<string, mixed>> $planned_topics
 * @property int|null $document_file_id
 * @property string $status
 * @property int|null $reviewed_by
 * @property string|null $review_comments
 */
class SchemeOfWork extends Model
{
    use BelongsToSchool;

    protected $table = 'schemes_of_work';

    /** @use HasFactory<SchemeOfWorkFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'subject_id', 'grade_level_id', 'teacher_staff_id',
        'planned_topics', 'document_file_id', 'status', 'reviewed_by', 'review_comments',
    ];

    protected function casts(): array
    {
        return [
            'planned_topics' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SchemeOfWorkFactory::new();
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
     * @return BelongsTo<Staff, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'teacher_staff_id');
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(File::class, 'document_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<SyllabusCoverageRecord, $this>
     */
    public function coverageRecords(): HasMany
    {
        return $this->hasMany(SyllabusCoverageRecord::class);
    }

    /**
     * @return HasMany<LessonPlan, $this>
     */
    public function lessonPlans(): HasMany
    {
        return $this->hasMany(LessonPlan::class);
    }
}
