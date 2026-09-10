<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Casts\SecondaryEncrypted;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\ConsultationFactory;

/**
 * Book G BRD-06 §2 — Tier 3. `presenting_complaint`, `assessment` and
 * `plan` are `SecondaryEncrypted`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property int|null $admission_id
 * @property Carbon $consulted_at
 * @property string $consultation_type
 * @property string $presenting_complaint
 * @property string|null $assessment
 * @property string|null $plan
 * @property string $practitioner_type
 * @property int|null $practitioner_staff_id
 * @property string|null $external_practitioner
 * @property Carbon|null $follow_up_on
 */
class Consultation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ConsultationFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'student_id', 'admission_id', 'consulted_at', 'consultation_type',
        'presenting_complaint', 'assessment', 'plan', 'practitioner_type', 'practitioner_staff_id',
        'external_practitioner', 'follow_up_on',
    ];

    protected function casts(): array
    {
        return [
            'consulted_at' => 'datetime',
            'presenting_complaint' => SecondaryEncrypted::class,
            'assessment' => SecondaryEncrypted::class,
            'plan' => SecondaryEncrypted::class,
            'follow_up_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ConsultationFactory::new();
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
     * @return BelongsTo<Staff, $this>
     */
    public function practitionerStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'practitioner_staff_id');
    }
}
