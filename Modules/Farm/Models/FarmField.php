<?php

declare(strict_types=1);

namespace Modules\Farm\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Farm\Database\Factories\FarmFieldFactory;
use Modules\Utilities\Models\WaterSource;

/**
 * Book H2 OPS-03 §2/BR-OPS-03-018. Named `FarmField`, not the spec's
 * own literal `Field` — a bare `Field` model name is exactly the kind
 * of collision-prone generic name this codebase avoids (see
 * `Modules\Operations\Models\CapitalProjectMilestone`'s own migration
 * docblock for the same reasoning applied to a table name instead of
 * a class name here). The table itself stays the spec's own `fields`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $production_unit_id
 * @property string $code
 * @property string $name
 * @property float $area_hectares
 * @property string|null $soil_type
 * @property bool $is_irrigated
 * @property string|null $irrigation_type
 * @property int|null $water_source_id
 * @property Carbon|null $last_soil_test_on
 * @property string|null $notes
 */
class FarmField extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FarmFieldFactory> */
    use HasFactory;

    protected $table = 'fields';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'production_unit_id', 'code', 'name', 'area_hectares', 'soil_type', 'is_irrigated',
        'irrigation_type', 'water_source_id', 'last_soil_test_on', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'area_hectares' => 'decimal:4',
            'is_irrigated' => 'boolean',
            'last_soil_test_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FarmFieldFactory::new();
    }

    /**
     * @return BelongsTo<ProductionUnit, $this>
     */
    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    /**
     * @return BelongsTo<WaterSource, $this>
     */
    public function waterSource(): BelongsTo
    {
        return $this->belongsTo(WaterSource::class);
    }
}
