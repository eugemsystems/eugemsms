<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Payroll\Database\Factories\PayComponentFactory;

/**
 * Book H3 PPL-05 §2/§3 ⭐ — the tax-treatment flags
 * `StatutoryCalculationEngine` reads to build each base.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $component_type
 * @property string $category
 * @property string $calculation_method
 * @property int|null $default_amount_minor
 * @property string|null $default_percent
 * @property string|null $currency
 * @property bool $is_taxable
 * @property bool $is_pensionable
 * @property bool $is_nec_applicable
 * @property bool $is_zimdef_applicable
 * @property string $taxable_percent
 * @property bool $is_active
 */
class PayComponent extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PayComponentFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'component_type', 'category', 'calculation_method',
        'default_amount_minor', 'default_percent', 'formula', 'currency', 'is_taxable',
        'is_pensionable', 'is_nec_applicable', 'is_zimdef_applicable', 'taxable_percent',
        'expense_account_id', 'liability_account_id', 'cost_centre_source', 'appears_on_payslip',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_percent' => 'decimal:3',
            'is_taxable' => 'boolean',
            'is_pensionable' => 'boolean',
            'is_nec_applicable' => 'boolean',
            'is_zimdef_applicable' => 'boolean',
            'taxable_percent' => 'decimal:2',
            'appears_on_payslip' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PayComponentFactory::new();
    }
}
