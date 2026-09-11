<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Term;
use Modules\Finance\Database\Factories\AwardUtilisationFactory;

/**
 * Book K FIN-07 §2/BR-FIN-07-013 — APPEND-ONLY. See the owning
 * migration's docblock for why this is a model-level guard rather
 * than a DB grant REVOKE in this pass (same pattern as `Journal`/
 * `SafeguardingAuditEntry`).
 *
 * @property int $id
 * @property int $school_id
 * @property int $award_id
 * @property int $term_id
 * @property int $component_id
 * @property int $discount_minor
 * @property string $currency
 * @property int $fee_line_id
 * @property int $journal_id
 * @property Carbon $posted_at
 */
class AwardUtilisation extends Model
{
    use BelongsToSchool;

    protected $table = 'award_utilisation';

    /** @use HasFactory<AwardUtilisationFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'award_id', 'term_id', 'component_id', 'discount_minor', 'currency',
        'fee_line_id', 'journal_id', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_minor' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AwardUtilisationFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('award_utilisation is append-only and can never be updated.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('award_utilisation is append-only and can never be deleted.');
        });
    }

    /**
     * @return BelongsTo<DiscountAward, $this>
     */
    public function award(): BelongsTo
    {
        return $this->belongsTo(DiscountAward::class, 'award_id');
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<FeeComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(FeeComponent::class);
    }

    /**
     * @return BelongsTo<LearnerFeeLine, $this>
     */
    public function feeLine(): BelongsTo
    {
        return $this->belongsTo(LearnerFeeLine::class, 'fee_line_id');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
