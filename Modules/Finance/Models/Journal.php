<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;
use Modules\Finance\Database\Factories\JournalFactory;

/**
 * Book B FIN-01 §2/BR-FIN-01-012. Header row. `journals` permits
 * `UPDATE` on `status` and `reversed_by_journal_id` only — real
 * enforcement is `booted()` below (see the migration's note on why the
 * spec's DB trigger is a deployment-time control, not a portable
 * migration).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $journal_number
 * @property string $journal_type
 * @property string $narration
 * @property string|null $reference
 * @property string|null $source_type
 * @property int|null $source_id
 * @property Carbon $effective_at
 * @property Carbon $posted_at
 * @property bool $is_prior_period_adjustment
 * @property bool $is_reversal
 * @property int|null $reverses_journal_id
 * @property int|null $reversed_by_journal_id
 * @property string|null $reversal_reason
 * @property string $status
 * @property int $posted_by
 * @property int|null $approved_by
 * @property string|null $batch_uuid
 */
class Journal extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<JournalFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    /**
     * A real class property, not an Eloquent attribute — same shape as
     * `BelongsToSession::$periodOverrideApproved`, which this model
     * deliberately doesn't use as a trait: that trait's automatic
     * `updating` hook would also gate the narrow `reversed_by_journal_id`
     * backlink update against the *original* journal's period, which is
     * routinely locked by the time a reversal happens (that's the whole
     * point of cross-period reversal, BR-FIN-01-014). Actions call
     * `PeriodGuard::assertWritable()` explicitly, only at the point a new
     * journal is actually created.
     */
    public bool $periodOverrideApproved = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'journal_number', 'journal_type',
        'narration', 'reference', 'source_type', 'source_id', 'effective_at', 'posted_at',
        'is_prior_period_adjustment', 'is_reversal', 'reverses_journal_id',
        'reversed_by_journal_id', 'reversal_reason', 'status', 'posted_by', 'approved_by',
        'batch_uuid', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'date',
            'posted_at' => 'datetime',
            'is_prior_period_adjustment' => 'boolean',
            'is_reversal' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return JournalFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            // `approved_by` is a necessary addition to the spec's stated
            // two permitted columns (§2's "-- No UPDATE except status,
            // reversed_by_journal_id"): a draft manual journal has no
            // approver until `ApproveManualJournalAction` runs, and that
            // action is the module's own described workflow (BR-FIN-01-018)
            // — the spec is silent on how `approved_by` gets set otherwise.
            $permitted = ['status', 'reversed_by_journal_id', 'approved_by'];

            if (array_diff($dirty, $permitted) !== []) {
                throw new InvalidStateTransitionException(
                    'journals permits updating status, reversed_by_journal_id, and approved_by only — every other column is immutable.',
                    ['dirty' => $dirty],
                );
            }
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('journals is append-only and can never be deleted.');
        });
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
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
     * @return BelongsTo<self, $this>
     */
    public function reversesJournal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_journal_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function reversedByJournal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_journal_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isReversed(): bool
    {
        return $this->reversed_by_journal_id !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
