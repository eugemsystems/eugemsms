<?php

declare(strict_types=1);

namespace Modules\Utilities\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Utilities\Database\Factories\UtilityAccountFactory;

/**
 * Book H2 OPS-04 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $utility_type
 * @property string $provider
 * @property string $account_number
 * @property string|null $tariff_code
 * @property string $billing_mode
 * @property int $cost_centre_id
 * @property int $expense_account_id
 * @property bool $is_active
 */
class UtilityAccount extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<UtilityAccountFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'utility_type', 'provider', 'account_number', 'tariff_code', 'billing_mode',
        'cost_centre_id', 'expense_account_id', 'is_active',
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
        return UtilityAccountFactory::new();
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    /**
     * @return HasMany<Meter, $this>
     */
    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }
}
