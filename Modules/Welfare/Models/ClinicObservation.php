<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Welfare\Database\Factories\ClinicObservationFactory;

/**
 * Book G BRD-06 §2 — APPEND-ONLY vitals chart. See
 * `Modules\Core\Models\FinancialAuditLogEntry` for why the model-level
 * guard, not a DB grant REVOKE, is what's enforced and tested here.
 *
 * @property int $id
 * @property int $school_id
 * @property int $admission_id
 * @property Carbon $observed_at
 * @property float|null $temperature_c
 * @property int|null $pulse_bpm
 * @property int|null $respiration_rate
 * @property string|null $blood_pressure
 * @property int|null $oxygen_saturation
 * @property int|null $pain_score
 * @property string|null $notes
 * @property int $observed_by
 */
class ClinicObservation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ClinicObservationFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'admission_id', 'observed_at', 'temperature_c', 'pulse_bpm', 'respiration_rate',
        'blood_pressure', 'oxygen_saturation', 'pain_score', 'notes', 'observed_by',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'temperature_c' => 'decimal:1',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('clinic_observations is append-only and can never be updated.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('clinic_observations is append-only and can never be deleted.');
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ClinicObservationFactory::new();
    }

    /**
     * @return BelongsTo<SickBayAdmission, $this>
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(SickBayAdmission::class, 'admission_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function observedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'observed_by');
    }
}
