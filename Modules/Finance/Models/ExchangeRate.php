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
use Modules\Finance\Database\Factories\ExchangeRateFactory;

/**
 * Book B FIN-06 §2/BR-FIN-06-004. Append-only except for the four
 * columns a correction actually touches — see `journals`' migration
 * (FIN-01) for why the real DB-grant version of this control is a
 * deployment step, not a portable migration.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $source_id
 * @property string $from_currency
 * @property string $to_currency
 * @property string $rate
 * @property string $inverse_rate
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $status
 * @property int $captured_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $notes
 */
class ExchangeRate extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'source_id', 'from_currency', 'to_currency', 'rate', 'inverse_rate',
        'effective_from', 'effective_to', 'status', 'captured_by', 'approved_by',
        'approved_at', 'notes', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            // Explicit `decimal:N` guarantees a fixed-precision STRING —
            // without it, this driver returns the DECIMAL(20,10) column
            // as a PHP float, which is exactly the precision loss
            // bcmath-based Money arithmetic exists to avoid.
            'rate' => 'decimal:10',
            'inverse_rate' => 'decimal:10',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExchangeRateFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $permitted = ['effective_to', 'status', 'approved_by', 'approved_at', 'notes'];

            if (array_diff($dirty, $permitted) !== []) {
                throw new InvalidStateTransitionException(
                    'exchange_rates permits updating effective_to, status, approved_by, approved_at, and notes only — a corrected rate is a new row.',
                    ['dirty' => $dirty],
                );
            }
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('exchange_rates is append-only and can never be deleted.');
        });
    }

    /**
     * @return BelongsTo<ExchangeRateSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ExchangeRateSource::class, 'source_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
