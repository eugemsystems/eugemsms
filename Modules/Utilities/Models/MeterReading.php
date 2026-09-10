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
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Utilities\Database\Factories\MeterReadingFactory;

/**
 * Book H2 OPS-04 §2/BR-OPS-04-006 — APPEND-ONLY.
 *
 * @property int $id
 * @property int $school_id
 * @property int $meter_id
 * @property Carbon $read_on
 * @property float $reading
 * @property float|null $previous_reading
 * @property float|null $consumption
 * @property int|null $days_since_last
 * @property float|null $daily_average
 * @property string $reading_method
 * @property int|null $photo_file_id
 * @property int $read_by
 * @property bool $is_anomaly
 * @property string|null $anomaly_note
 */
class MeterReading extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MeterReadingFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'meter_id', 'read_on', 'reading', 'previous_reading', 'consumption', 'days_since_last',
        'daily_average', 'reading_method', 'photo_file_id', 'read_by', 'is_anomaly', 'anomaly_note',
    ];

    protected function casts(): array
    {
        return [
            'read_on' => 'date',
            'reading' => 'decimal:3',
            'previous_reading' => 'decimal:3',
            'consumption' => 'decimal:3',
            'daily_average' => 'decimal:3',
            'is_anomaly' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('meter_readings is append-only and can never be updated (BR-OPS-04-006).');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('meter_readings is append-only and can never be deleted (BR-OPS-04-006).');
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MeterReadingFactory::new();
    }

    /**
     * @return BelongsTo<Meter, $this>
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function readBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by');
    }
}
