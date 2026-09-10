<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Payroll\Database\Factories\StaffPayComponentFactory;

/**
 * Book H3 PPL-05 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $pay_structure_id
 * @property int $component_id
 * @property int|null $amount_minor
 * @property string|null $percent
 * @property string $currency
 * @property string|null $quantity
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
class StaffPayComponent extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffPayComponentFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'pay_structure_id', 'component_id', 'amount_minor', 'percent', 'currency',
        'quantity', 'effective_from', 'effective_to', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'percent' => 'decimal:3',
            'quantity' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StaffPayComponentFactory::new();
    }

    /**
     * @return BelongsTo<PayComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class);
    }

    /**
     * @return BelongsTo<StaffPayStructure, $this>
     */
    public function payStructure(): BelongsTo
    {
        return $this->belongsTo(StaffPayStructure::class, 'pay_structure_id');
    }
}
