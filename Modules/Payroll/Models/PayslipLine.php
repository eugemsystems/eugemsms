<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Payroll\Database\Factories\PayslipLineFactory;

/**
 * Book H3 PPL-05 §2 — fully immutable, like `InvoiceLine`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $payslip_id
 * @property int|null $component_id
 * @property string $component_type
 * @property string $description
 * @property string|null $quantity
 * @property int|null $rate_minor
 * @property int $amount_minor
 * @property string $currency
 * @property bool $is_taxable
 * @property int|null $sort_order
 */
class PayslipLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PayslipLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'payslip_id', 'component_id', 'component_type', 'description', 'quantity',
        'rate_minor', 'amount_minor', 'currency', 'is_taxable', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'is_taxable' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PayslipLineFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InvalidStateTransitionException('A payslip line is fully immutable once created.');
        });
    }

    /**
     * @return BelongsTo<Payslip, $this>
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    /**
     * @return BelongsTo<PayComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class);
    }
}
