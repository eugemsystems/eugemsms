<?php

declare(strict_types=1);

namespace Modules\Farm\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Farm\Database\Factories\ProductionUnitFactory;
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Staff;
use Modules\Stores\Models\Store;

/**
 * Book H2 OPS-03 §2 ⭐/BR-OPS-03-001.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $unit_type
 * @property int $cost_centre_id
 * @property int|null $manager_staff_id
 * @property int|null $store_id
 * @property float|null $area_hectares
 * @property bool $is_active
 */
class ProductionUnit extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ProductionUnitFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'unit_type', 'cost_centre_id', 'manager_staff_id', 'store_id',
        'area_hectares', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'area_hectares' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ProductionUnitFactory::new();
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'manager_staff_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<FarmField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FarmField::class);
    }

    /**
     * @return HasMany<CropCycle, $this>
     */
    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }

    /**
     * @return HasMany<Livestock, $this>
     */
    public function livestock(): HasMany
    {
        return $this->hasMany(Livestock::class);
    }
}
