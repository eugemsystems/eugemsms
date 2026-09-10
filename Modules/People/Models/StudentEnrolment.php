<?php

declare(strict_types=1);

namespace Modules\People\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\House;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Database\Factories\StudentEnrolmentFactory;

/**
 * Book C PPL-01 §2. The term-by-term enrolment history.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $section_id
 * @property int $grade_level_id
 * @property int|null $class_id
 * @property int|null $house_id
 * @property string $enrolment_type
 * @property string $residency
 * @property string|null $pathway
 * @property string $status
 * @property Carbon|null $started_on
 * @property Carbon|null $ended_on
 * @property bool $is_repeat
 */
class StudentEnrolment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentEnrolmentFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'student_id', 'academic_year_id', 'term_id', 'section_id',
        'grade_level_id', 'class_id', 'house_id', 'enrolment_type', 'residency',
        'pathway', 'status', 'started_on', 'ended_on', 'is_repeat', 'attendance_percent',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
            'is_repeat' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StudentEnrolmentFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
     * @return BelongsTo<SchoolSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
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
     * @return BelongsTo<House, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
