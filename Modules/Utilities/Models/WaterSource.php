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
use Modules\Utilities\Database\Factories\WaterSourceFactory;

/**
 * Book H2 OPS-04 §2/BR-OPS-04-015/016/017.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $source_type
 * @property float|null $depth_metres
 * @property float|null $yield_litres_per_hour
 * @property string|null $pump_capacity
 * @property float|null $storage_capacity_litres
 * @property int|null $maintenance_asset_id
 * @property string $status
 * @property Carbon|null $last_tested_on
 * @property string|null $water_quality_status
 * @property Carbon|null $last_quality_test_on
 */
class WaterSource extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WaterSourceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'source_type', 'depth_metres', 'yield_litres_per_hour',
        'pump_capacity', 'storage_capacity_litres', 'maintenance_asset_id', 'status', 'last_tested_on',
        'water_quality_status', 'last_quality_test_on',
    ];

    protected function casts(): array
    {
        return [
            'depth_metres' => 'decimal:2',
            'yield_litres_per_hour' => 'decimal:2',
            'storage_capacity_litres' => 'decimal:2',
            'last_tested_on' => 'date',
            'last_quality_test_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WaterSourceFactory::new();
    }

    /**
     * @return BelongsTo<MaintenanceAsset, $this>
     */
    public function maintenanceAsset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class);
    }

    /**
     * @return HasMany<WaterReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(WaterReading::class);
    }
}
