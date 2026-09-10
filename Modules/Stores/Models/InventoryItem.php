<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\FeeComponent;
use Modules\Stores\Database\Factories\InventoryItemFactory;

/**
 * Book H1 FIN-09 §2/§3 ⭐ — the inventory/fixed-asset boundary lives on
 * `is_capitalisable`/`capitalisation_threshold_minor`, evaluated on
 * issue by `IssueStockAction`, never at receipt.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int|null $category_id
 * @property string $base_unit
 * @property string|null $purchase_unit
 * @property float $purchase_conversion
 * @property string|null $issue_unit
 * @property float $issue_conversion
 * @property bool $is_perishable
 * @property bool $requires_batch_tracking
 * @property int|null $shelf_life_days
 * @property bool $is_high_risk
 * @property bool $is_saleable
 * @property int|null $sale_price_minor
 * @property string|null $sale_currency
 * @property int|null $sale_fee_component_id
 * @property bool $is_capitalisable
 * @property int|null $capitalisation_threshold_minor
 * @property int|null $expense_account_id
 * @property int|null $preferred_supplier_id
 * @property int|null $standard_cost_minor
 * @property string|null $standard_cost_currency
 * @property string|null $barcode
 * @property int|null $image_file_id
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class InventoryItem extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'code', 'name', 'description', 'category_id', 'base_unit', 'purchase_unit',
        'purchase_conversion', 'issue_unit', 'issue_conversion', 'is_perishable', 'requires_batch_tracking',
        'shelf_life_days', 'is_high_risk', 'is_saleable', 'sale_price_minor', 'sale_currency',
        'sale_fee_component_id', 'is_capitalisable', 'capitalisation_threshold_minor', 'expense_account_id',
        'preferred_supplier_id', 'standard_cost_minor', 'standard_cost_currency', 'barcode', 'image_file_id',
        'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_conversion' => 'decimal:6',
            'issue_conversion' => 'decimal:6',
            'is_perishable' => 'boolean',
            'requires_batch_tracking' => 'boolean',
            'is_high_risk' => 'boolean',
            'is_saleable' => 'boolean',
            'is_capitalisable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return InventoryItemFactory::new();
    }

    /**
     * @return BelongsTo<ItemCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<FeeComponent, $this>
     */
    public function saleFeeComponent(): BelongsTo
    {
        return $this->belongsTo(FeeComponent::class, 'sale_fee_component_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
