<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Models\Account;
use Modules\Stores\Database\Factories\ItemCategoryFactory;

/**
 * Book H1 FIN-09 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int|null $parent_id
 * @property string $code
 * @property string $name
 * @property int|null $default_expense_account_id
 * @property bool $is_active
 */
class ItemCategory extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ItemCategoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'parent_id', 'code', 'name', 'default_expense_account_id', 'is_active'];

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
        return ItemCategoryFactory::new();
    }

    /**
     * @return BelongsTo<ItemCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function defaultExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_expense_account_id');
    }
}
