<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Database\Factories\AwardDiscountCommitmentFactory;

/**
 * Book K FIN-07 — bridges a discount computed at billing-preview time
 * (`ComputeBillingRunAction`, via `AwardDiscountResolver`) to the
 * append-only `AwardUtilisation` row `IssueInvoicesForAssignmentAction`
 * creates once the fee line is actually invoiced and journaled. See
 * the owning migration's docblock for the full reasoning — this is a
 * deliberate, documented extension beyond the spec's own §2 table
 * listing, not a spec table.
 *
 * @property int $id
 * @property int $school_id
 * @property int $fee_line_id
 * @property int $award_id
 * @property int $scheme_id
 * @property int $discount_minor
 * @property string $currency
 */
class AwardDiscountCommitment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AwardDiscountCommitmentFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'fee_line_id', 'award_id', 'scheme_id', 'discount_minor', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'discount_minor' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AwardDiscountCommitmentFactory::new();
    }

    /**
     * @return BelongsTo<LearnerFeeLine, $this>
     */
    public function feeLine(): BelongsTo
    {
        return $this->belongsTo(LearnerFeeLine::class, 'fee_line_id');
    }

    /**
     * @return BelongsTo<DiscountAward, $this>
     */
    public function award(): BelongsTo
    {
        return $this->belongsTo(DiscountAward::class, 'award_id');
    }

    /**
     * @return BelongsTo<DiscountScheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(DiscountScheme::class, 'scheme_id');
    }
}
