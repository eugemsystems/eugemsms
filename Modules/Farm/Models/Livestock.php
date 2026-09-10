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
use Modules\Farm\Database\Factories\LivestockFactory;
use Modules\Stores\Models\FixedAsset;

/**
 * Book H2 OPS-03 §2/BR-OPS-03-010/011.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $production_unit_id
 * @property string|null $tag_number
 * @property string $species
 * @property string|null $breed
 * @property bool $is_herd_record
 * @property int $head_count
 * @property string|null $sex
 * @property Carbon|null $date_of_birth
 * @property Carbon|null $acquired_on
 * @property string|null $acquisition_type
 * @property int|null $acquisition_cost_minor
 * @property string|null $currency
 * @property string $purpose
 * @property string $status
 * @property Carbon|null $disposal_on
 * @property string|null $disposal_reason
 * @property int|null $fixed_asset_id
 */
class Livestock extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LivestockFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'livestock';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'production_unit_id', 'tag_number', 'species', 'breed', 'is_herd_record', 'head_count',
        'sex', 'date_of_birth', 'acquired_on', 'acquisition_type', 'acquisition_cost_minor', 'currency',
        'purpose', 'status', 'disposal_on', 'disposal_reason', 'fixed_asset_id',
    ];

    protected function casts(): array
    {
        return [
            'is_herd_record' => 'boolean',
            'date_of_birth' => 'date',
            'acquired_on' => 'date',
            'disposal_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LivestockFactory::new();
    }

    /**
     * @return BelongsTo<ProductionUnit, $this>
     */
    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    /**
     * @return BelongsTo<FixedAsset, $this>
     */
    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    /**
     * @return HasMany<LivestockEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(LivestockEvent::class);
    }
}
