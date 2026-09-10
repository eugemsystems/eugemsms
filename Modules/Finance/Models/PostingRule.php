<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Database\Factories\PostingRuleFactory;

/**
 * Book B FIN-01 §4. Maps a domain event to the accounts it posts to.
 * The code refers to `system_key` and resolvers, never hard-coded
 * account codes, so a school can rebind any mapping to its own chart.
 *
 * @property int $id
 * @property int $school_id
 * @property string $event_key
 * @property int|null $debit_account_id
 * @property int|null $credit_account_id
 * @property string|null $debit_resolver
 * @property string|null $credit_resolver
 * @property int|null $cost_centre_id
 * @property bool $is_active
 */
class PostingRule extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PostingRuleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'event_key', 'debit_account_id', 'credit_account_id',
        'debit_resolver', 'credit_resolver', 'cost_centre_id', 'is_active',
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
        return PostingRuleFactory::new();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }
}
