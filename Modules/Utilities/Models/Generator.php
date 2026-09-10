<?php

declare(strict_types=1);

namespace Modules\Utilities\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\CostCentre;
use Modules\Operations\Models\MaintenanceAsset;
use Modules\Stores\Models\FixedAsset;
use Modules\Utilities\Database\Factories\GeneratorFactory;

/**
 * Book H2 OPS-04 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property float $capacity_kva
 * @property string $fuel_type
 * @property float|null $tank_capacity_litres
 * @property float|null $expected_litres_per_hour
 * @property string $serves_scope
 * @property int|null $scope_id
 * @property float $current_hours
 * @property int|null $fixed_asset_id
 * @property int|null $maintenance_asset_id
 * @property int $cost_centre_id
 * @property string $status
 */
class Generator extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GeneratorFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'capacity_kva', 'fuel_type', 'tank_capacity_litres',
        'expected_litres_per_hour', 'serves_scope', 'scope_id', 'current_hours', 'fixed_asset_id',
        'maintenance_asset_id', 'cost_centre_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'capacity_kva' => 'decimal:2',
            'tank_capacity_litres' => 'decimal:2',
            'expected_litres_per_hour' => 'decimal:3',
            'current_hours' => 'decimal:2',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return GeneratorFactory::new();
    }

    /**
     * @return BelongsTo<FixedAsset, $this>
     */
    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    /**
     * @return BelongsTo<MaintenanceAsset, $this>
     */
    public function maintenanceAsset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class);
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return HasMany<GeneratorRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(GeneratorRun::class);
    }
}
