<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\TermSubjectResultFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * Book D ACA-05 §2/§3 ⭐ — a cache, rebuilt by
 * `ComputeTermSubjectResultsAction` from `assessment_marks`. Never
 * itself trusted, same discipline as every rebuild-only cache table
 * in this codebase.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $student_id
 * @property int $subject_id
 * @property string|null $coursework_percent
 * @property string|null $examination_percent
 * @property string|null $continuous_percent
 * @property string|null $final_percent
 * @property string|null $grade
 * @property string|null $points
 * @property int|null $class_position
 * @property int|null $class_size
 * @property int|null $level_position
 * @property string|null $subject_average
 * @property string|null $teacher_comment
 * @property int|null $teacher_staff_id
 * @property string|null $sbp_outcome
 * @property string|null $sbp_grade
 * @property bool $is_finalised
 * @property Carbon|null $computed_at
 */
class TermSubjectResult extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TermSubjectResultFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'student_id', 'subject_id',
        'coursework_percent', 'examination_percent', 'continuous_percent', 'final_percent',
        'grade', 'points', 'class_position', 'class_size', 'level_position', 'subject_average',
        'teacher_comment', 'teacher_staff_id', 'sbp_outcome', 'sbp_grade', 'is_finalised', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_finalised' => 'boolean',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TermSubjectResultFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
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
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'teacher_staff_id');
    }
}
