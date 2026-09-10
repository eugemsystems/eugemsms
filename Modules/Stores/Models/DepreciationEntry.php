<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Database\Factories\DepreciationEntryFactory;

/**
 * @property int $id
 * @property int $school_id
 * @property int $run_id
 * @property int $asset_id
 * @property int $opening_nbv_minor
 * @property int $depreciation_minor
 * @property int $closing_nbv_minor
 * @property string $method_used
 */
class DepreciationEntry extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DepreciationEntryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'run_id', 'asset_id', 'opening_nbv_minor', 'depreciation_minor', 'closing_nbv_minor',
        'method_used', 'calculation_note',
    ];

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DepreciationEntryFactory::new();
    }

    /**
     * @return BelongsTo<DepreciationRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(DepreciationRun::class, 'run_id');
    }

    /**
     * @return BelongsTo<FixedAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }
}
