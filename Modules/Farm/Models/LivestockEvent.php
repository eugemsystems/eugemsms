<?php

declare(strict_types=1);

namespace Modules\Farm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Farm\Database\Factories\LivestockEventFactory;

/**
 * Book H2 OPS-03 §2 ⭐/BR-OPS-03-012/013/014 — APPEND-ONLY.
 *
 * @property int $id
 * @property int $school_id
 * @property int $livestock_id
 * @property string $event_type
 * @property Carbon $event_date
 * @property int $head_count_affected
 * @property string|null $description
 * @property string|null $medication
 * @property string|null $dosage
 * @property int|null $withdrawal_period_days
 * @property Carbon|null $withdrawal_ends_on
 * @property float|null $weight_kg
 * @property int|null $cost_minor
 * @property string|null $performed_by
 * @property int $recorded_by
 */
class LivestockEvent extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LivestockEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'livestock_id', 'event_type', 'event_date', 'head_count_affected', 'description',
        'medication', 'dosage', 'withdrawal_period_days', 'withdrawal_ends_on', 'weight_kg', 'cost_minor',
        'performed_by', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'withdrawal_ends_on' => 'date',
            'weight_kg' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('livestock_events is append-only and can never be updated (BR-OPS-03-012).');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('livestock_events is append-only and can never be deleted (BR-OPS-03-012).');
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LivestockEventFactory::new();
    }

    /**
     * @return BelongsTo<Livestock, $this>
     */
    public function livestock(): BelongsTo
    {
        return $this->belongsTo(Livestock::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
