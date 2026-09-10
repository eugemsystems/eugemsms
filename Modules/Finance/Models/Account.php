<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Support\Currency;
use Modules\Finance\Database\Factories\AccountFactory;

/**
 * Book B FIN-01 §2. The chart of accounts.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $parent_id
 * @property int $account_type_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_postable
 * @property bool $is_control_account
 * @property string|null $subledger_type
 * @property bool $is_system
 * @property string|null $system_key
 * @property string|null $currency
 * @property bool $requires_cost_centre
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class Account extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'parent_id', 'account_type_id', 'code', 'name', 'description',
        'is_postable', 'is_control_account', 'subledger_type', 'is_system', 'system_key',
        'currency', 'requires_cost_centre', 'is_active', 'opened_on', 'closed_on',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_postable' => 'boolean',
            'is_control_account' => 'boolean',
            'is_system' => 'boolean',
            'requires_cost_centre' => 'boolean',
            'is_active' => 'boolean',
            'opened_on' => 'date',
            'closed_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AccountFactory::new();
    }

    /**
     * @return BelongsTo<AccountType, $this>
     */
    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function restrictedCurrency(): ?Currency
    {
        return $this->currency !== null ? Currency::from($this->currency) : null;
    }
}
