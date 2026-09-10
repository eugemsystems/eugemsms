<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\SickBayAdmissionFactory;

/**
 * Book G BRD-06 §2/BR-BRD-06-015/016 ⭐ — closes the `sick_bay`
 * roll-status stub `BRD-02` §4 left open (`status = 'admitted'` or
 * `'observing'` is what `BRD-02`'s `OpenRollCallAction` queries).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $student_id
 * @property Carbon $admitted_at
 * @property int $admitted_by
 * @property string $presenting_complaint
 * @property array<string, mixed>|null $initial_observations
 * @property string|null $bed_reference
 * @property bool $is_isolation
 * @property string|null $isolation_reason
 * @property string $severity
 * @property Carbon|null $guardian_notified_at
 * @property int|null $guardian_notified_by
 * @property Carbon|null $expected_discharge_at
 * @property Carbon|null $discharged_at
 * @property int|null $discharged_by
 * @property string|null $discharge_destination
 * @property string|null $discharge_notes
 * @property bool $excused_from_lessons
 * @property bool $excused_from_activity
 * @property string $status
 */
class SickBayAdmission extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SickBayAdmissionFactory> */
    use HasFactory;

    use HasUlid;

    public const array CURRENTLY_ADMITTED_STATUSES = ['admitted', 'observing', 'referred'];

    public const array NOTIFY_IMMEDIATELY_SEVERITIES = ['serious', 'emergency'];

    protected $fillable = [
        'school_id', 'term_id', 'student_id', 'admitted_at', 'admitted_by', 'presenting_complaint',
        'initial_observations', 'bed_reference', 'is_isolation', 'isolation_reason', 'severity',
        'guardian_notified_at', 'guardian_notified_by', 'expected_discharge_at', 'discharged_at',
        'discharged_by', 'discharge_destination', 'discharge_notes', 'excused_from_lessons',
        'excused_from_activity', 'status',
    ];

    protected function casts(): array
    {
        return [
            'admitted_at' => 'datetime',
            'initial_observations' => 'array',
            'is_isolation' => 'boolean',
            'guardian_notified_at' => 'datetime',
            'expected_discharge_at' => 'datetime',
            'discharged_at' => 'datetime',
            'excused_from_lessons' => 'boolean',
            'excused_from_activity' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SickBayAdmissionFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
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
    public function admittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }

    /**
     * @return HasMany<ClinicObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(ClinicObservation::class, 'admission_id');
    }
}
