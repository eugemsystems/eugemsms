<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\BehaviourRecordFactory;

/**
 * Book G BRD-07 §2/§3 ⭐/BR-BRD-07-001/016/017/018.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $student_id
 * @property int $category_id
 * @property string $polarity
 * @property int $points
 * @property Carbon $occurred_at
 * @property string|null $location
 * @property string|null $context
 * @property int|null $subject_id
 * @property int|null $class_id
 * @property int|null $hostel_id
 * @property string $description
 * @property array<int, string>|null $witnesses
 * @property array<int, int>|null $other_learners_involved
 * @property array<int, int>|null $evidence_file_ids
 * @property int $reported_by
 * @property string $status
 * @property Carbon|null $guardian_notified_at
 * @property bool $is_confidential
 * @property int|null $safeguarding_case_id
 */
class BehaviourRecord extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BehaviourRecordFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'student_id', 'category_id', 'polarity', 'points',
        'occurred_at', 'location', 'context', 'subject_id', 'class_id', 'hostel_id', 'description',
        'witnesses', 'other_learners_involved', 'evidence_file_ids', 'reported_by', 'status',
        'guardian_notified_at', 'is_confidential', 'safeguarding_case_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'witnesses' => 'array',
            'other_learners_involved' => 'array',
            'evidence_file_ids' => 'array',
            'guardian_notified_at' => 'datetime',
            'is_confidential' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BehaviourRecordFactory::new();
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
     * @return BelongsTo<BehaviourCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BehaviourCategory::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
