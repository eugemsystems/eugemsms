<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Casts\SecondaryEncrypted;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\MedicalRecordFactory;

/**
 * Book G BRD-06 §2 — Tier 3. `notes` and `medical_aid_number` are
 * `SecondaryEncrypted`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property string|null $blood_group
 * @property int|null $height_cm
 * @property float|null $weight_kg
 * @property Carbon|null $last_measured_on
 * @property string|null $medical_aid_provider
 * @property string|null $medical_aid_number
 * @property string|null $medical_aid_principal
 * @property string|null $family_doctor_name
 * @property string|null $family_doctor_phone
 * @property string|null $preferred_hospital
 * @property string|null $notes
 * @property Carbon|null $last_reviewed_at
 * @property int|null $last_reviewed_by
 */
class MedicalRecord extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MedicalRecordFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'student_id', 'blood_group', 'height_cm', 'weight_kg', 'last_measured_on',
        'medical_aid_provider', 'medical_aid_number', 'medical_aid_principal', 'family_doctor_name',
        'family_doctor_phone', 'preferred_hospital', 'notes', 'last_reviewed_at', 'last_reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'height_cm' => 'integer',
            'weight_kg' => 'decimal:2',
            'last_measured_on' => 'date',
            'medical_aid_number' => SecondaryEncrypted::class,
            'notes' => SecondaryEncrypted::class,
            'last_reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MedicalRecordFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lastReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reviewed_by');
    }
}
