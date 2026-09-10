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
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Staff;
use Modules\Stores\Database\Factories\StoreFactory;

/**
 * Book H1 FIN-09 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $store_type
 * @property int|null $custodian_staff_id
 * @property int $cost_centre_id
 * @property int $inventory_account_id
 * @property int $default_expense_account_id
 * @property string|null $location
 * @property string $costing_method
 * @property bool $requires_issue_approval
 * @property bool $allows_negative_stock
 * @property bool $is_active
 * @property int|null $created_by
 */
class Store extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'code', 'name', 'store_type', 'custodian_staff_id', 'cost_centre_id',
        'inventory_account_id', 'default_expense_account_id', 'location', 'costing_method',
        'requires_issue_approval', 'allows_negative_stock', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'requires_issue_approval' => 'boolean',
            'allows_negative_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StoreFactory::new();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function custodian(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'custodian_staff_id');
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
    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'inventory_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function defaultExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_expense_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
