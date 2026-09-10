<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Database\Factories\BankAccountFactory;

/**
 * Book B FIN-05 §3 — a real-world bank account, distinct from its
 * `Account` GL counterpart.
 *
 * @property int $id
 * @property int $school_id
 * @property int $gl_account_id
 * @property string $bank_name
 * @property string $account_name
 * @property string $account_number
 * @property string|null $branch
 * @property string $currency
 * @property string $account_type
 * @property bool $is_active
 */
class BankAccount extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'gl_account_id', 'bank_name', 'account_name', 'account_number',
        'branch', 'currency', 'account_type', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BankAccountFactory::new();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }
}
