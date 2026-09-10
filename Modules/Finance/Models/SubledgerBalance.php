<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Term;
use Modules\Finance\Database\Factories\SubledgerBalanceFactory;

/**
 * Book B FIN-01 §3. Cache only — never authoritative.
 *
 * @property int $id
 * @property int $school_id
 * @property string $subledger_type
 * @property int $subledger_id
 * @property int $account_id
 * @property int $term_id
 * @property string $currency
 * @property int $opening_minor
 * @property int $debit_minor
 * @property int $credit_minor
 * @property int $closing_minor
 * @property Carbon $rebuilt_at
 */
class SubledgerBalance extends Model
{
    /** @use HasFactory<SubledgerBalanceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'subledger_type', 'subledger_id', 'account_id', 'term_id',
        'currency', 'opening_minor', 'debit_minor', 'credit_minor', 'closing_minor',
        'rebuilt_at',
    ];

    protected function casts(): array
    {
        return [
            'rebuilt_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SubledgerBalanceFactory::new();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
