<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Database\Factories\BankStatementLineFactory;

/**
 * Book B FIN-05 §3/BR-FIN-05-011/012.
 *
 * @property int $id
 * @property int $school_id
 * @property int $statement_id
 * @property int $line_number
 * @property Carbon $transaction_date
 * @property Carbon|null $value_date
 * @property string $description
 * @property string|null $reference
 * @property int|null $debit_minor
 * @property int|null $credit_minor
 * @property int|null $running_balance_minor
 * @property string $currency
 * @property string $match_status
 * @property string|null $matched_type
 * @property int|null $matched_id
 * @property int|null $match_confidence
 * @property int|null $matched_by
 * @property Carbon|null $matched_at
 */
class BankStatementLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BankStatementLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'statement_id', 'line_number', 'transaction_date', 'value_date',
        'description', 'reference', 'debit_minor', 'credit_minor', 'running_balance_minor',
        'currency', 'match_status', 'matched_type', 'matched_id', 'match_confidence',
        'matched_by', 'matched_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'value_date' => 'date',
            'matched_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BankStatementLineFactory::new();
    }

    /**
     * @return BelongsTo<BankStatement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'statement_id');
    }

    public function isCredit(): bool
    {
        return $this->credit_minor !== null && $this->credit_minor > 0;
    }
}
