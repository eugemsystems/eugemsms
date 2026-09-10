<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\StudentPriorResultFactory;

/**
 * Book C §2 "carried achievement" — an externally examined result the
 * student brings with them, e.g. a ZIMSEC Grade 7 or O-Level subject
 * grade. Populated by Book H3 CMP-01's `ImportZimsecResultsAction`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int|null $prior_school_id
 * @property string $examination
 * @property int $exam_year
 * @property string|null $candidate_number
 * @property string $subject
 * @property string $grade
 * @property string|null $points
 * @property bool $is_verified
 */
class StudentPriorResult extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentPriorResultFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'student_id', 'prior_school_id', 'examination', 'exam_year',
        'candidate_number', 'subject', 'grade', 'points', 'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'exam_year' => 'integer',
            'points' => 'decimal:2',
            'is_verified' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StudentPriorResultFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
