<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Database\Factories\BankStatementFactory;

/**
 * Book B FIN-05 §3.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $bank_account_id
 * @property Carbon $statement_from
 * @property Carbon $statement_to
 * @property int $opening_balance_minor
 * @property int $closing_balance_minor
 * @property string $currency
 * @property int $line_count
 * @property int $matched_count
 * @property string $status
 * @property int $imported_by
 * @property int|null $reconciled_by
 * @property Carbon|null $reconciled_at
 */
class BankStatement extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BankStatementFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'bank_account_id', 'statement_from', 'statement_to',
        'opening_balance_minor', 'closing_balance_minor', 'currency', 'source_file_id',
        'line_count', 'matched_count', 'status', 'imported_by', 'reconciled_by',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'statement_from' => 'date',
            'statement_to' => 'date',
            'line_count' => 'integer',
            'matched_count' => 'integer',
            'reconciled_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BankStatementFactory::new();
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return HasMany<BankStatementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'statement_id');
    }
}
