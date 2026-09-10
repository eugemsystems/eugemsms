<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Database\Factories\FeeComponentFactory;

/**
 * Book B FIN-02 §2 — the fee catalogue.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $category
 * @property int $income_account_id
 * @property int $debtor_account_id
 * @property int|null $cost_centre_id
 * @property string $default_currency
 * @property bool $is_refundable
 * @property bool $is_mandatory
 * @property bool $is_fiscalisable
 * @property string $tax_category
 * @property int $allocation_priority
 * @property bool $counts_toward_report_gate
 * @property bool $is_active
 */
class FeeComponent extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FeeComponentFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'code', 'name', 'description', 'category', 'income_account_id',
        'debtor_account_id', 'cost_centre_id', 'default_currency', 'is_refundable',
        'is_mandatory', 'is_fiscalisable', 'tax_category', 'allocation_priority',
        'counts_toward_report_gate', 'is_active', 'sort_order', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_refundable' => 'boolean',
            'is_mandatory' => 'boolean',
            'is_fiscalisable' => 'boolean',
            'allocation_priority' => 'integer',
            'counts_toward_report_gate' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FeeComponentFactory::new();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function debtorAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'debtor_account_id');
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }
}
