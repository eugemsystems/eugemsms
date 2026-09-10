<?php

declare(strict_types=1);

namespace Modules\Utilities\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Utilities\Database\Factories\LoadSheddingScheduleFactory;

/**
 * Book H2 OPS-04 §2 🇿🇼.
 *
 * @property int $id
 * @property int $school_id
 * @property Carbon $schedule_date
 * @property string|null $stage
 * @property string $starts_at
 * @property string $ends_at
 * @property Carbon|null $actual_outage_start
 * @property Carbon|null $actual_outage_end
 * @property bool|null $was_scheduled
 * @property string $source
 * @property string|null $impact_note
 */
class LoadSheddingSchedule extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LoadSheddingScheduleFactory> */
    use HasFactory;

    protected $table = 'load_shedding_schedule';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'schedule_date', 'stage', 'starts_at', 'ends_at', 'actual_outage_start',
        'actual_outage_end', 'was_scheduled', 'source', 'impact_note',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
            'actual_outage_start' => 'datetime',
            'actual_outage_end' => 'datetime',
            'was_scheduled' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LoadSheddingScheduleFactory::new();
    }
}
