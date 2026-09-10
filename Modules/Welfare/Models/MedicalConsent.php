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
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\MedicalConsentFactory;

/**
 * Book G BRD-06 §2/BR-BRD-06-011/012 ⭐.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property int $guardian_id
 * @property string $consent_type
 * @property string|null $scope_detail
 * @property bool $granted
 * @property Carbon $granted_at
 * @property string $granted_via
 * @property int|null $witness_staff_id
 * @property int|null $document_file_id
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property Carbon|null $withdrawn_at
 * @property string|null $withdrawn_reason
 */
class MedicalConsent extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MedicalConsentFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'student_id', 'guardian_id', 'consent_type', 'scope_detail', 'granted',
        'granted_at', 'granted_via', 'witness_staff_id', 'document_file_id', 'effective_from',
        'effective_to', 'withdrawn_at', 'withdrawn_reason',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'granted_at' => 'datetime',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'withdrawn_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MedicalConsentFactory::new();
    }

    /**
     * BR-BRD-06-011 — a valid consent is granted, unwithdrawn, and
     * currently effective.
     */
    public function isValidNow(): bool
    {
        if (! $this->granted || $this->withdrawn_at !== null) {
            return false;
        }

        $today = now()->toDateString();

        if ($this->effective_from->toDateString() > $today) {
            return false;
        }

        return $this->effective_to === null || $this->effective_to->toDateString() >= $today;
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function witnessStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'witness_staff_id');
    }
}
