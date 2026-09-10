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
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\MedicationAdministrationFactory;

/**
 * Book G BRD-06 §2/BR-BRD-06-010 ⭐ — APPEND-ONLY LEGAL RECORD. See
 * `Modules\Core\Models\FinancialAuditLogEntry` for why this is a
 * model-level guard rather than a DB grant REVOKE in this pass.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property int|null $admission_id
 * @property int|null $prescription_id
 * @property string $medication_name
 * @property string $dose
 * @property string $route
 * @property Carbon|null $scheduled_at
 * @property Carbon $administered_at
 * @property int $administered_by
 * @property int|null $witnessed_by
 * @property string|null $batch_number
 * @property Carbon|null $expiry_date
 * @property string|null $consent_reference
 * @property string|null $outcome
 * @property string|null $omission_reason
 * @property string|null $adverse_reaction
 * @property string|null $notes
 */
class MedicationAdministration extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MedicationAdministrationFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'admission_id', 'prescription_id', 'medication_name', 'dose', 'route',
        'scheduled_at', 'administered_at', 'administered_by', 'witnessed_by', 'batch_number',
        'expiry_date', 'consent_reference', 'outcome', 'omission_reason', 'adverse_reaction', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'administered_at' => 'datetime',
            'expiry_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('medication_administrations is append-only and can never be updated.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('medication_administrations is append-only and can never be deleted.');
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MedicationAdministrationFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<SickBayAdmission, $this>
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(SickBayAdmission::class, 'admission_id');
    }

    /**
     * @return BelongsTo<Prescription, $this>
     */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function witnessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witnessed_by');
    }
}
