<?php

declare(strict_types=1);

namespace Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\Till;
use Modules\Stores\Models\Store;
use Modules\Wallet\Database\Factories\SpendPointFactory;

/**
 * Book H3 FIN-14 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $point_type
 * @property int|null $store_id
 * @property int|null $till_id
 * @property int $income_account_id
 * @property int $cost_centre_id
 * @property bool $is_fiscalisable
 * @property array<string, mixed>|null $operating_hours
 * @property bool $is_active
 */
class SpendPoint extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SpendPointFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'point_type', 'store_id', 'till_id', 'income_account_id',
        'cost_centre_id', 'is_fiscalisable', 'operating_hours', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_fiscalisable' => 'boolean',
            'operating_hours' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SpendPointFactory::new();
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Till, $this>
     */
    public function till(): BelongsTo
    {
        return $this->belongsTo(Till::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }
}
