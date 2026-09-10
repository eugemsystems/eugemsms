<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\ExeatFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * Book F BRD-03 §2/§3/§4 ⭐ — the chain of custody of a child.
 * `collecting_person_id_no` is encrypted at rest.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $exeat_number
 * @property int $student_id
 * @property int $exeat_type_id
 * @property int|null $requested_by_guardian_id
 * @property int|null $requested_by_user_id
 * @property string $request_source
 * @property string $reason
 * @property int|null $supporting_document_id
 * @property Carbon $departs_at
 * @property Carbon $returns_by
 * @property string $destination_address
 * @property string|null $destination_city
 * @property string|null $destination_province
 * @property string $destination_country
 * @property string $contact_phone
 * @property int|null $collecting_guardian_id
 * @property string|null $collecting_person_name
 * @property string|null $collecting_person_id_no
 * @property string|null $collecting_person_phone
 * @property string $collection_method
 * @property int|null $one_off_authorisation_by
 * @property string $status
 * @property int|null $approval_request_id
 * @property string|null $rejection_reason
 * @property int|null $pass_document_id
 * @property string|null $verification_code
 * @property Carbon|null $actual_departure_at
 * @property int|null $departure_recorded_by
 * @property string|null $departure_verified_by
 * @property Carbon|null $actual_return_at
 * @property int|null $return_recorded_by
 * @property Carbon|null $overdue_notified_at
 * @property int|null $late_return_minutes
 */
class Exeat extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ExeatFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'exeat_number', 'student_id', 'exeat_type_id',
        'requested_by_guardian_id', 'requested_by_user_id', 'request_source', 'reason',
        'supporting_document_id', 'departs_at', 'returns_by', 'destination_address',
        'destination_city', 'destination_province', 'destination_country', 'contact_phone',
        'collecting_guardian_id', 'collecting_person_name', 'collecting_person_id_no',
        'collecting_person_phone', 'collection_method', 'one_off_authorisation_by', 'status',
        'approval_request_id', 'rejection_reason', 'pass_document_id', 'verification_code',
        'actual_departure_at', 'departure_recorded_by', 'departure_verified_by',
        'actual_return_at', 'return_recorded_by', 'overdue_notified_at', 'late_return_minutes',
    ];

    protected function casts(): array
    {
        return [
            'departs_at' => 'datetime',
            'returns_by' => 'datetime',
            'collecting_person_id_no' => 'encrypted',
            'actual_departure_at' => 'datetime',
            'actual_return_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExeatFactory::new();
    }

    public function isApprovedAndCurrent(): bool
    {
        if ($this->status !== 'approved' && $this->status !== 'departed') {
            return false;
        }

        $now = Carbon::now();

        return $now->greaterThanOrEqualTo($this->departs_at->copy()->subHours(6)) && $now->lessThanOrEqualTo($this->returns_by);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
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
     * @return BelongsTo<ExeatType, $this>
     */
    public function exeatType(): BelongsTo
    {
        return $this->belongsTo(ExeatType::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function collectingGuardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'collecting_guardian_id');
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function oneOffAuthorisationBy(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'one_off_authorisation_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function departureRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'departure_recorded_by');
    }
}
