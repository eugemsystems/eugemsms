<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Database\Factories\NumberingSeriesFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book A CORE-06 §2/§3.
 *
 * @property int $id
 * @property int $school_id
 * @property string $document_type
 * @property int|null $academic_year_id
 * @property int|null $term_id
 * @property string $pattern
 * @property string|null $prefix
 * @property int $next_sequence
 * @property int $sequence_padding
 * @property string $reset_policy
 * @property bool $is_active
 * @property-read School $school
 * @property-read AcademicYear|null $academicYear
 * @property-read Term|null $term
 */
class NumberingSeries extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<NumberingSeriesFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'document_type', 'academic_year_id', 'term_id', 'pattern',
        'prefix', 'next_sequence', 'sequence_padding', 'reset_policy', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'next_sequence' => 'integer',
            'sequence_padding' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return NumberingSeriesFactory::new();
    }

    /**
     * @return HasMany<AllocatedNumber, $this>
     */
    public function allocatedNumbers(): HasMany
    {
        return $this->hasMany(AllocatedNumber::class, 'series_id');
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
     * Book A CORE-06 §3 `forCurrentPeriod()` — matches this document
     * type's series for the currently active year/term where the
     * series is period-scoped, or the single year/term-less series
     * otherwise.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForPeriod(Builder $query, ?int $academicYearId, ?int $termId): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('academic_year_id')->orWhere('academic_year_id', $academicYearId))
            ->where(fn ($q) => $q->whereNull('term_id')->orWhere('term_id', $termId));
    }
}
