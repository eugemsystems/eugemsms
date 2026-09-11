<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\Account;
use Modules\People\Database\Factories\CapitalCampaignFactory;

/**
 * Book K PPL-06 §2/BR-PPL-06-010. `raised_amount_minor` is derived
 * from `donations` — only `RecordDonationAction` ever moves it.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $name
 * @property string $purpose
 * @property int $target_amount_minor
 * @property int $raised_amount_minor
 * @property string $currency
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property int $income_account_id
 * @property string $status
 */
class CapitalCampaign extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CapitalCampaignFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'name', 'purpose', 'target_amount_minor', 'raised_amount_minor', 'currency',
        'starts_on', 'ends_on', 'income_account_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'target_amount_minor' => 'integer',
            'raised_amount_minor' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CapitalCampaignFactory::new();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    /**
     * @return HasMany<Pledge, $this>
     */
    public function pledges(): HasMany
    {
        return $this->hasMany(Pledge::class, 'campaign_id');
    }
}
