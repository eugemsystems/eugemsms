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
use Modules\Academic\Database\Factories\ExaminationSessionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;

/**
 * Book E ACA-07 §2. One examination series.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $name
 * @property string $exam_type
 * @property string $exam_body
 * @property array<int, int> $affected_levels
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string|null $index_number_pattern
 * @property int|null $exam_slot_plan_id
 * @property string $status
 * @property int $created_by
 */
class ExaminationSession extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ExaminationSessionFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'name', 'exam_type', 'exam_body',
        'affected_levels', 'starts_on', 'ends_on', 'index_number_pattern',
        'exam_slot_plan_id', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'affected_levels' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExaminationSessionFactory::new();
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
     * @return BelongsTo<ExamSlotPlan, $this>
     */
    public function examSlotPlan(): BelongsTo
    {
        return $this->belongsTo(ExamSlotPlan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ExaminationPaper, $this>
     */
    public function papers(): HasMany
    {
        return $this->hasMany(ExaminationPaper::class, 'session_id');
    }

    /**
     * @return HasMany<ExaminationCandidate, $this>
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(ExaminationCandidate::class, 'session_id');
    }
}
