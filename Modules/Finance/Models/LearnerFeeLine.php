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
use Modules\Finance\Database\Factories\LearnerFeeLineFactory;

/**
 * Book B FIN-02 §2/§4 ⭐/BR-FIN-02-009. One computed charge — the
 * `calculation_note` is what a bursar reads back to a parent who
 * queries their bill. Immutable once written; a correction is a new
 * line (typically negative, for a credit), never an edit to this one.
 *
 * @property int $id
 * @property int $school_id
 * @property int $assignment_id
 * @property int $component_id
 * @property int|null $structure_item_id
 * @property string $billing_basis
 * @property string $quantity
 * @property int|null $unit_rate_minor
 * @property int $gross_minor
 * @property string $proration_factor
 * @property int $discount_minor
 * @property int $net_minor
 * @property string $currency
 * @property string|null $calculation_note
 * @property string|null $source_reference
 * @property Carbon|null $effective_from
 */
class LearnerFeeLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LearnerFeeLineFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'assignment_id', 'component_id', 'structure_item_id', 'billing_basis',
        'quantity', 'unit_rate_minor', 'gross_minor', 'proration_factor', 'discount_minor',
        'net_minor', 'currency', 'calculation_note', 'source_reference', 'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'proration_factor' => 'decimal:6',
            'effective_from' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LearnerFeeLineFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InvalidStateTransitionException(
                'learner_fee_lines is immutable once written — a correction is a new line, never an edit (BR-FIN-02-009).',
                [],
            );
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException(
                'learner_fee_lines rows are never deleted.',
                [],
            );
        });
    }

    /**
     * @return BelongsTo<LearnerFeeAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LearnerFeeAssignment::class, 'assignment_id');
    }

    /**
     * @return BelongsTo<FeeComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(FeeComponent::class);
    }

    /**
     * @return BelongsTo<FeeStructureItem, $this>
     */
    public function structureItem(): BelongsTo
    {
        return $this->belongsTo(FeeStructureItem::class, 'structure_item_id');
    }
}
