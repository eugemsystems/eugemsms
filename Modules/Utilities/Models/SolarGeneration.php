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
use Modules\Utilities\Database\Factories\SolarGenerationFactory;

/**
 * Book H2 OPS-04 §2/BR-OPS-04-014.
 *
 * @property int $id
 * @property int $school_id
 * @property int $installation_id
 * @property Carbon $record_date
 * @property float $kwh_generated
 * @property float|null $kwh_consumed
 * @property float|null $battery_state_percent
 * @property float|null $grid_offset_kwh
 * @property int|null $recorded_by
 * @property string $reading_method
 */
class SolarGeneration extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SolarGenerationFactory> */
    use HasFactory;

    protected $table = 'solar_generation';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'installation_id', 'record_date', 'kwh_generated', 'kwh_consumed',
        'battery_state_percent', 'grid_offset_kwh', 'recorded_by', 'reading_method',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'kwh_generated' => 'decimal:3',
            'kwh_consumed' => 'decimal:3',
            'battery_state_percent' => 'decimal:2',
            'grid_offset_kwh' => 'decimal:3',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SolarGenerationFactory::new();
    }

    /**
     * @return BelongsTo<SolarInstallation, $this>
     */
    public function installation(): BelongsTo
    {
        return $this->belongsTo(SolarInstallation::class, 'installation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
