<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Book B FIN-01 §2. Seeded, global, never school-scoped.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $normal_balance
 * @property string $statement
 * @property int $sort_order
 */
class AccountType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code', 'name', 'normal_balance', 'statement', 'sort_order',
    ];

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function isDebitNormal(): bool
    {
        return $this->normal_balance === 'DR';
    }
}
