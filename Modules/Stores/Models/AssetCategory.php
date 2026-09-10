<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Models\Account;
use Modules\Stores\Database\Factories\AssetCategoryFactory;

/**
 * Book H1 FIN-10 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property int $asset_account_id
 * @property int $accum_depreciation_account_id
 * @property int $depreciation_expense_account_id
 * @property int $disposal_account_id
 * @property string $default_method
 * @property float|null $default_useful_life_years
 * @property float $default_residual_percent
 * @property bool $is_depreciable
 * @property int $verification_frequency_months
 */
class AssetCategory extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssetCategoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'asset_account_id', 'accum_depreciation_account_id',
        'depreciation_expense_account_id', 'disposal_account_id', 'default_method',
        'default_useful_life_years', 'default_residual_percent', 'is_depreciable',
        'verification_frequency_months',
    ];

    protected function casts(): array
    {
        return [
            'default_useful_life_years' => 'decimal:1',
            'default_residual_percent' => 'decimal:2',
            'is_depreciable' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AssetCategoryFactory::new();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function accumDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accum_depreciation_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_expense_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function disposalAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'disposal_account_id');
    }
}
