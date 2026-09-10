<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\AcademicYearFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\GuardsPeriodStateWrites;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Domain\Support\PeriodType;

/**
 * Book A CORE-03 §2. The year axis of the session engine — everything
 * academic or financial reads its temporal context from here and `Term`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property bool $is_current
 * @property PeriodState $academic_state
 * @property PeriodState $financial_state
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Term> $terms
 * @property-read Collection<int, CalendarHoliday> $holidays
 */
class AcademicYear extends Model
{
    use BelongsToSchool;
    use GuardsPeriodStateWrites;

    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id',
        'name',
        'starts_on',
        'ends_on',
        'is_current',
        'academic_state',
        'financial_state',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
            'academic_state' => PeriodState::class,
            'financial_state' => PeriodState::class,
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AcademicYearFactory::new();
    }

    /**
     * @return HasMany<Term, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function currentTerm(): ?Term
    {
        return $this->terms()->where('is_current', true)->first();
    }

    /**
     * @return HasMany<CalendarHoliday, $this>
     */
    public function holidays(): HasMany
    {
        return $this->hasMany(CalendarHoliday::class);
    }

    public function stateFor(PeriodType $type): PeriodState
    {
        return $type === PeriodType::Academic
            ? $this->academic_state
            : $this->financial_state;
    }
}
