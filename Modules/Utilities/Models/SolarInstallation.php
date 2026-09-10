<?php

declare(strict_types=1);

namespace Modules\Utilities\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Operations\Models\MaintenanceAsset;
use Modules\Stores\Models\FixedAsset;
use Modules\Utilities\Database\Factories\SolarInstallationFactory;

/**
 * Book H2 OPS-04 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property float $capacity_kwp
 * @property float|null $battery_capacity_kwh
 * @property string $serves_scope
 * @property int|null $scope_id
 * @property Carbon|null $commissioned_on
 * @property int|null $fixed_asset_id
 * @property int|null $maintenance_asset_id
 * @property string $status
 */
class SolarInstallation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SolarInstallationFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'capacity_kwp', 'battery_capacity_kwh', 'serves_scope', 'scope_id',
        'commissioned_on', 'fixed_asset_id', 'maintenance_asset_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'capacity_kwp' => 'decimal:2',
            'battery_capacity_kwh' => 'decimal:2',
            'commissioned_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SolarInstallationFactory::new();
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
     * @return HasMany<SolarGeneration, $this>
     */
    public function generation(): HasMany
    {
        return $this->hasMany(SolarGeneration::class, 'installation_id');
    }
}
