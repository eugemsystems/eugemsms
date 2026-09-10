<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\TermResultFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book D ACA-05 §2/§3/§4 ⭐ — overall per learner per term, rebuilt by
 * `ComputeTermResultsAction`. `status` moves draft→computed→reviewed→
 * approved→published, or →withheld (BR-ACA-05-014/015 — a withheld
 * report is still generated and stored, just never published).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $student_id
 * @property int $class_id
 * @property int $subjects_taken
 * @property int $subjects_passed
 * @property string|null $total_marks
 * @property string|null $average_percent
 * @property string|null $total_points
 * @property int|null $aggregate
 * @property int|null $class_position
 * @property int|null $class_size
 * @property int|null $level_position
 * @property int|null $level_size
 * @property string|null $attendance_percent
 * @property string|null $conduct_grade
 * @property string|null $class_teacher_comment
 * @property string|null $head_comment
 * @property string|null $promotion_recommendation
 * @property string $status
 * @property string|null $withheld_reason
 * @property int|null $report_document_id
 * @property Carbon|null $published_at
 * @property int|null $approved_by
 */
class TermResult extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TermResultFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'student_id', 'class_id', 'subjects_taken',
        'subjects_passed', 'total_marks', 'average_percent', 'total_points', 'aggregate',
        'class_position', 'class_size', 'level_position', 'level_size', 'attendance_percent',
        'conduct_grade', 'class_teacher_comment', 'head_comment', 'promotion_recommendation',
        'status', 'withheld_reason', 'report_document_id', 'published_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TermResultFactory::new();
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
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
