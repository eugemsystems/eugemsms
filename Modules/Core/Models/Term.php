<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\TermFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\GuardsPeriodStateWrites;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Domain\Support\PeriodType;

/**
 * Book A CORE-03 §2. A term is not a semester: Zimbabwe runs three terms
 * per calendar year, and that word is not used anywhere in this system
 * (Volume 1 §0.3).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $number
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property Carbon|null $half_term_starts_on
 * @property Carbon|null $half_term_ends_on
 * @property int|null $teaching_days
 * @property Carbon|null $fee_due_on
 * @property Carbon|null $results_due_on
 * @property Carbon|null $reports_release_on
 * @property bool $is_current
 * @property PeriodState $academic_state
 * @property PeriodState $financial_state
 * @property Carbon|null $academic_closed_at
 * @property int|null $academic_closed_by
 * @property Carbon|null $financial_closed_at
 * @property int|null $financial_closed_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AcademicYear $academicYear
 * @property-read Collection<int, TermWeek> $weeks
 */
class Term extends Model
{
    use BelongsToSchool;
    use GuardsPeriodStateWrites;

    /** @use HasFactory<TermFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'number',
        'name',
        'starts_on',
        'ends_on',
        'half_term_starts_on',
        'half_term_ends_on',
        'teaching_days',
        'fee_due_on',
        'results_due_on',
        'reports_release_on',
        'is_current',
        'academic_state',
        'financial_state',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'half_term_starts_on' => 'date',
            'half_term_ends_on' => 'date',
            'teaching_days' => 'integer',
            'fee_due_on' => 'date',
            'results_due_on' => 'date',
            'reports_release_on' => 'date',
            'is_current' => 'boolean',
            'academic_state' => PeriodState::class,
            'financial_state' => PeriodState::class,
            'academic_closed_at' => 'datetime',
            'financial_closed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TermFactory::new();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return HasMany<TermWeek, $this>
     */
    public function weeks(): HasMany
    {
        return $this->hasMany(TermWeek::class);
    }

    public function stateFor(PeriodType $type): PeriodState
    {
        return $type === PeriodType::Academic
            ? $this->academic_state
            : $this->financial_state;
    }
}
