<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;
use Modules\Finance\Database\Factories\FeeStructureFactory;

/**
 * Book B FIN-02 §2/BR-FIN-02-011/012. A versioned billing structure.
 * `term_id` is deliberately nullable ("applies to all terms in the
 * year") — see the migration docblock for why this model uses
 * `BelongsToSchool` only and calls `PeriodGuard::assertWritable()`
 * explicitly rather than `BelongsToSession`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int|null $term_id
 * @property string $name
 * @property int $version
 * @property string $status
 * @property int $priority
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property-read Collection<int, FeeStructureRule> $rules
 * @property-read Collection<int, FeeStructureItem> $items
 */
class FeeStructure extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FeeStructureFactory> */
    use HasFactory;

    use HasUlid;

    /**
     * BR-FIN-02-011: a new version is a new row, never an edit to
     * `version` on an existing one.
     */
    private const array IMMUTABLE_ONCE_ACTIVE = ['version', 'academic_year_id', 'term_id'];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'name', 'version', 'status',
        'priority', 'effective_from', 'effective_to', 'approved_by', 'approved_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'priority' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FeeStructureFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Model $model): void {
            PeriodGuard::assertWritable($model);
        });

        static::updating(function (Model $model): void {
            PeriodGuard::assertWritable($model);

            $dirty = array_keys($model->getDirty());
            $illegal = array_intersect($dirty, self::IMMUTABLE_ONCE_ACTIVE);

            if ($illegal !== [] && $model->getOriginal('status') !== 'draft') {
                throw new InvalidStateTransitionException(
                    'version, academic_year_id, and term_id are immutable once a structure has left draft (BR-FIN-02-011) — edit by creating a new version.',
                    ['dirty' => $illegal],
                );
            }
        });
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
     * @return HasMany<FeeStructureRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(FeeStructureRule::class, 'structure_id');
    }

    /**
     * @return HasMany<FeeStructureItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FeeStructureItem::class, 'structure_id');
    }
}
