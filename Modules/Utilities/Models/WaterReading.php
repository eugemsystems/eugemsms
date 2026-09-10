<?php

declare(strict_types=1);

namespace Modules\Utilities\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Utilities\Database\Factories\WaterReadingFactory;

/**
 * Book H2 OPS-04 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $water_source_id
 * @property Carbon $read_on
 * @property float|null $storage_level_percent
 * @property float|null $volume_pumped_litres
 * @property float|null $pump_hours
 * @property float|null $yield_observed
 * @property string|null $notes
 * @property int $read_by
 */
class WaterReading extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WaterReadingFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'water_source_id', 'read_on', 'storage_level_percent', 'volume_pumped_litres',
        'pump_hours', 'yield_observed', 'notes', 'read_by',
    ];

    protected function casts(): array
    {
        return [
            'read_on' => 'date',
            'storage_level_percent' => 'decimal:2',
            'volume_pumped_litres' => 'decimal:2',
            'pump_hours' => 'decimal:2',
            'yield_observed' => 'decimal:2',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WaterReadingFactory::new();
    }

    /**
     * @return BelongsTo<WaterSource, $this>
     */
    public function waterSource(): BelongsTo
    {
        return $this->belongsTo(WaterSource::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function readBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by');
    }
}
