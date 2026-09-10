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
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\HealthIncidentFactory;

/**
 * Book G BRD-06 §2/BR-BRD-06-020/021.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $student_id
 * @property string $incident_type
 * @property Carbon $occurred_at
 * @property string $location
 * @property string|null $activity_at_time
 * @property string $description
 * @property array<int, mixed>|null $witnesses
 * @property string|null $first_aid_given
 * @property int|null $first_aider_staff_id
 * @property int|null $admission_id
 * @property int|null $referral_id
 * @property int|null $fixture_id Book H2 OPS-07 §3/BR-OPS-07-012 — additive, nullable; see that migration's docblock.
 * @property Carbon|null $guardian_notified_at
 * @property string $severity
 * @property bool $is_reportable
 * @property string|null $reported_to
 * @property Carbon|null $reported_at
 * @property bool $follow_up_required
 * @property array<int, int>|null $photo_file_ids
 * @property int $reported_by
 */
class HealthIncident extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HealthIncidentFactory> */
    use HasFactory;

    use HasUlid;

    public const array NOTIFY_HEAD_SEVERITIES = ['serious', 'critical'];

    protected $fillable = [
        'school_id', 'term_id', 'student_id', 'incident_type', 'occurred_at', 'location',
        'activity_at_time', 'description', 'witnesses', 'first_aid_given', 'first_aider_staff_id',
        'admission_id', 'referral_id', 'guardian_notified_at', 'severity', 'is_reportable',
        'reported_to', 'reported_at', 'follow_up_required', 'photo_file_ids', 'reported_by',
        'fixture_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'witnesses' => 'array',
            'guardian_notified_at' => 'datetime',
            'is_reportable' => 'boolean',
            'reported_at' => 'datetime',
            'follow_up_required' => 'boolean',
            'photo_file_ids' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HealthIncidentFactory::new();
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
     * @return BelongsTo<Staff, $this>
     */
    public function firstAiderStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'first_aider_staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
