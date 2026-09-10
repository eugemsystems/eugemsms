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
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\Term;
use Modules\Finance\Database\Factories\JournalLineFactory;

/**
 * Book B FIN-01 §2/BR-FIN-01-011. APPEND-ONLY, no exceptions — not even
 * the two-column exception `journals` gets.
 *
 * @property int $id
 * @property int $school_id
 * @property int $journal_id
 * @property int $line_number
 * @property int $account_id
 * @property int|null $cost_centre_id
 * @property string $direction
 * @property int $amount_minor
 * @property string $currency
 * @property int $base_amount_minor
 * @property string $base_currency
 * @property string $exchange_rate
 * @property int|null $exchange_rate_id
 * @property string|null $subledger_type
 * @property int|null $subledger_id
 * @property string|null $narration
 * @property Carbon $effective_at
 * @property int $term_id
 */
class JournalLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<JournalLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'journal_id', 'line_number', 'account_id', 'cost_centre_id',
        'direction', 'amount_minor', 'currency', 'base_amount_minor', 'base_currency',
        'exchange_rate', 'exchange_rate_id', 'subledger_type', 'subledger_id',
        'narration', 'effective_at', 'term_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return JournalLineFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('journal_lines is append-only and can never be updated.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('journal_lines is append-only and can never be deleted.');
        });
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function isDebit(): bool
    {
        return $this->direction === 'DR';
    }

    public function money(): Money
    {
        return Money::of($this->amount_minor, Currency::from($this->currency));
    }

    public function baseMoney(): Money
    {
        return Money::of($this->base_amount_minor, Currency::from($this->base_currency));
    }

    /**
     * BR-FIN-01-022: signed amount in the account's own book — positive
     * for a debit, negative for a credit. Summing this over lines and
     * comparing against the account type's normal balance is how every
     * balance in this module is derived.
     */
    public function signedBaseMinor(): int
    {
        return $this->isDebit() ? $this->base_amount_minor : -$this->base_amount_minor;
    }
}
