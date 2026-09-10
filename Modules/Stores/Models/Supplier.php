<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\Account;
use Modules\Stores\Database\Factories\SupplierFactory;

/**
 * Book H1 FIN-08 §2/BR-FIN-08-001/002/026. `account_number` is
 * encrypted at rest via the native `'encrypted'` cast — the same
 * pattern `Modules\Finance\Models\PaymentGateway::credentials` and
 * `Modules\People\Models\Student`'s ID-number fields already use.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string|null $trading_name
 * @property string $supplier_type
 * @property string|null $vat_number
 * @property bool $is_vat_registered
 * @property string|null $account_number
 * @property string $preferred_currency
 * @property int|null $control_account_id
 * @property string $status
 * @property string|null $blacklist_reason
 * @property int|null $approved_by
 * @property int|null $created_by
 */
class Supplier extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'code', 'name', 'trading_name', 'supplier_type', 'bp_number', 'vat_number',
        'company_registration', 'is_vat_registered', 'contact_person', 'phone', 'email', 'address_line_1',
        'city', 'country', 'bank_name', 'bank_branch', 'account_number', 'account_name', 'swift_code',
        'mobile_money_number', 'preferred_currency', 'payment_terms_days', 'credit_limit_minor',
        'credit_limit_currency', 'category_ids', 'control_account_id', 'rating', 'on_time_delivery_pct',
        'quality_rejection_pct', 'last_evaluated_at', 'status', 'blacklist_reason', 'approved_by', 'notes',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_vat_registered' => 'boolean',
            'category_ids' => 'array',
            'rating' => 'decimal:2',
            'on_time_delivery_pct' => 'decimal:2',
            'quality_rejection_pct' => 'decimal:2',
            'last_evaluated_at' => 'datetime',
            'account_number' => 'encrypted',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === 'active';
    }

    public function canReceiveOrders(): bool
    {
        return in_array($this->status, ['active'], true);
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SupplierFactory::new();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function controlAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'control_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
