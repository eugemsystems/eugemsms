<?php

declare(strict_types=1);

namespace Modules\Farm\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Farm\Database\Factories\CropCycleFactory;

/**
 * Book H2 OPS-03 §2 ⭐/BR-OPS-03-005/007.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $production_unit_id
 * @property int $field_id
 * @property string $cycle_reference
 * @property string $crop
 * @property string|null $variety
 * @property string $season
 * @property float $area_planted_hectares
 * @property Carbon|null $planted_on
 * @property Carbon|null $expected_harvest_on
 * @property Carbon|null $actual_harvest_on
 * @property float|null $expected_yield_kg
 * @property float|null $actual_yield_kg
 * @property int $input_cost_minor
 * @property int $labour_cost_minor
 * @property int $overhead_cost_minor
 * @property int $total_cost_minor
 * @property int|null $cost_per_kg_minor
 * @property string $currency
 * @property string $status
 * @property string|null $failure_reason
 */
class CropCycle extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CropCycleFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'production_unit_id', 'field_id', 'cycle_reference', 'crop',
        'variety', 'season', 'area_planted_hectares', 'planted_on', 'expected_harvest_on',
        'actual_harvest_on', 'expected_yield_kg', 'actual_yield_kg', 'input_cost_minor', 'labour_cost_minor',
        'overhead_cost_minor', 'total_cost_minor', 'cost_per_kg_minor', 'currency', 'status', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'area_planted_hectares' => 'decimal:4',
            'planted_on' => 'date',
            'expected_harvest_on' => 'date',
            'actual_harvest_on' => 'date',
            'expected_yield_kg' => 'decimal:2',
            'actual_yield_kg' => 'decimal:2',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CropCycleFactory::new();
    }

    /**
     * @return BelongsTo<ProductionUnit, $this>
     */
    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    /**
     * @return BelongsTo<FarmField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(FarmField::class, 'field_id');
    }

    /**
     * @return HasMany<CropInput, $this>
     */
    public function inputs(): HasMany
    {
        return $this->hasMany(CropInput::class);
    }

    /**
     * @return HasMany<Harvest, $this>
     */
    public function harvests(): HasMany
    {
        return $this->hasMany(Harvest::class);
    }
}
