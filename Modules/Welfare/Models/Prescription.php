<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\PrescriptionFactory;

/**
 * Book G BRD-06 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property string $medication_name
 * @property string $dose
 * @property string $frequency
 * @property string $route
 * @property string $prescribed_by
 * @property Carbon $prescribed_on
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_prn
 * @property int|null $max_doses_per_day
 * @property int|null $prescription_file_id
 * @property int|null $guardian_consent_id
 * @property bool $is_self_administered
 * @property string|null $storage_location
 * @property string $status
 */
class Prescription extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PrescriptionFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'student_id', 'medication_name', 'dose', 'frequency', 'route', 'prescribed_by',
        'prescribed_on', 'starts_on', 'ends_on', 'is_prn', 'max_doses_per_day', 'prescription_file_id',
        'guardian_consent_id', 'is_self_administered', 'storage_location', 'status',
    ];

    protected function casts(): array
    {
        return [
            'prescribed_on' => 'date',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_prn' => 'boolean',
            'is_self_administered' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PrescriptionFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<MedicalConsent, $this>
     */
    public function guardianConsent(): BelongsTo
    {
        return $this->belongsTo(MedicalConsent::class, 'guardian_consent_id');
    }
}
