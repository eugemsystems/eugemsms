<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Database\Factories\CurrencyConversionFactory;

/**
 * Book B FIN-06 §2/BR-FIN-06-003. Append-only, no exceptions — the
 * audit trail that makes every conversion re-derivable and
 * challengeable.
 *
 * @property int $id
 * @property int $school_id
 * @property int|null $journal_line_id
 * @property string $context_type
 * @property int|null $context_id
 * @property string $from_currency
 * @property int $from_amount_minor
 * @property string $to_currency
 * @property int $to_amount_minor
 * @property int $exchange_rate_id
 * @property string $rate_used
 */
class CurrencyConversion extends Model
{
    /** @use HasFactory<CurrencyConversionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'journal_line_id', 'context_type', 'context_id', 'from_currency',
        'from_amount_minor', 'to_currency', 'to_amount_minor', 'exchange_rate_id',
        'rate_used', 'rate_effective_from', 'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'rate_effective_from' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CurrencyConversionFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('currency_conversions is append-only and can never be updated.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('currency_conversions is append-only and can never be deleted.');
        });
    }

    /**
     * @return BelongsTo<ExchangeRate, $this>
     */
    public function exchangeRate(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class);
    }

    /**
     * @return BelongsTo<JournalLine, $this>
     */
    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class);
    }
}
