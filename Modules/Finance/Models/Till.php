<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Database\Factories\TillFactory;

/**
 * Book B FIN-04 §2 — a physical or logical cash point.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string|null $location
 * @property int|null $bank_account_id
 * @property int $cash_account_id
 * @property array<int, string> $accepted_currencies
 * @property array<int, string> $accepted_tenders
 * @property bool $is_fiscalised
 * @property bool $is_active
 */
class Till extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TillFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'code', 'name', 'location', 'bank_account_id', 'cash_account_id',
        'accepted_currencies', 'accepted_tenders', 'is_fiscalised', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'accepted_currencies' => 'array',
            'accepted_tenders' => 'array',
            'is_fiscalised' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TillFactory::new();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }
}
