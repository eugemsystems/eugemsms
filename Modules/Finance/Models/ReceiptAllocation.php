<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Database\Factories\ReceiptAllocationFactory;

/**
 * Book B FIN-04 §2/§4/BR-FIN-04-016. Reallocation reverses a row
 * (`reversed_at`/`reversed_by`/`reversal_reason`) and creates a new
 * one — never edits or deletes the original.
 *
 * @property int $id
 * @property int $school_id
 * @property int $receipt_id
 * @property int|null $invoice_id
 * @property int|null $invoice_line_id
 * @property int|null $component_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $allocation_method
 * @property Carbon $allocated_at
 * @property int $allocated_by
 * @property Carbon|null $reversed_at
 * @property int|null $reversed_by
 * @property string|null $reversal_reason
 */
class ReceiptAllocation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReceiptAllocationFactory> */
    use HasFactory;

    public $timestamps = false;

    private const array MUTABLE_AFTER_CREATE = ['reversed_at', 'reversed_by', 'reversal_reason'];

    protected $fillable = [
        'school_id', 'receipt_id', 'invoice_id', 'invoice_line_id', 'component_id',
        'amount_minor', 'currency', 'allocation_method', 'allocated_at', 'allocated_by',
        'reversed_at', 'reversed_by', 'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'allocated_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReceiptAllocationFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A receipt_allocations row may only be reversed, never edited (BR-FIN-04-016).',
                    ['dirty' => $illegal],
                );
            }
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('receipt_allocations rows are never deleted.', []);
        });
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    /**
     * @return BelongsTo<Receipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    /**
     * @return BelongsTo<FeeComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(FeeComponent::class);
    }
}
