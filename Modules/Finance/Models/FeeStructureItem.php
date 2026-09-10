<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Database\Factories\FeeStructureItemFactory;

/**
 * Book B FIN-02 §2/§4 ⭐. WHAT is charged. `tier_bands` and
 * `subject_rate_map` are the whole of the full-time/part-time
 * distinction — see `FeeLineCalculator`, which branches on
 * `billing_basis` alone, never on enrolment type.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $structure_id
 * @property int $component_id
 * @property string $billing_basis
 * @property int|null $amount_minor
 * @property string $currency
 * @property int|null $unit_rate_minor
 * @property string|null $unit_label
 * @property int|null $minimum_minor
 * @property int|null $maximum_minor
 * @property array<int, array{from: int, to: int|null, rate?: int, multiplier?: float}>|null $tier_bands
 * @property array<string, int>|null $subject_rate_map
 * @property bool $is_prorated
 * @property string $proration_basis
 * @property string $charge_frequency
 * @property bool $is_optional
 */
class FeeStructureItem extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FeeStructureItemFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'structure_id', 'component_id', 'billing_basis', 'amount_minor',
        'currency', 'unit_rate_minor', 'unit_label', 'minimum_minor', 'maximum_minor',
        'tier_bands', 'subject_rate_map', 'is_prorated', 'proration_basis',
        'charge_frequency', 'is_optional', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tier_bands' => 'array',
            'subject_rate_map' => 'array',
            'is_prorated' => 'boolean',
            'is_optional' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FeeStructureItemFactory::new();
    }

    /**
     * @return BelongsTo<FeeStructure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class, 'structure_id');
    }

    /**
     * @return BelongsTo<FeeComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(FeeComponent::class);
    }
}
