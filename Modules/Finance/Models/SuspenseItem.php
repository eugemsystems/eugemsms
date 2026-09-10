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
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Database\Factories\SuspenseItemFactory;

/**
 * Book B FIN-04 §2 ⭐/BR-FIN-04-010/011. Unidentified money — never
 * held off-ledger. The cashier always confirms a match; the system
 * never auto-allocates suspense.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $receipt_id
 * @property string $source
 * @property int $amount_minor
 * @property string $currency
 * @property int $resolved_minor
 * @property string|null $reference_text
 * @property string|null $depositor_name
 * @property Carbon $deposit_date
 * @property string $status
 * @property array<int, array<string, mixed>>|null $suggested_matches
 * @property int $age_days
 * @property int|null $assigned_to
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolution_note
 */
class SuspenseItem extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SuspenseItemFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    private const array MUTABLE_AFTER_CREATE = [
        'resolved_minor', 'status', 'suggested_matches', 'age_days', 'assigned_to',
        'resolved_at', 'resolved_by', 'resolution_note',
    ];

    protected $fillable = [
        'school_id', 'receipt_id', 'source', 'amount_minor', 'currency', 'resolved_minor',
        'reference_text', 'depositor_name', 'deposit_date', 'status', 'suggested_matches',
        'age_days', 'assigned_to', 'resolved_at', 'resolved_by', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'deposit_date' => 'date',
            'suggested_matches' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SuspenseItemFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A suspense_items row may only change its resolution-tracking columns after creation.',
                    ['dirty' => $illegal],
                );
            }
        });
    }

    /**
     * @return BelongsTo<Receipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
