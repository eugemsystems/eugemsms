<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\GradeLevel;
use Modules\People\Database\Factories\ApplicationFactory;

/**
 * Book C PPL-02 §2/§3 ⭐. Every field mirrors its `Student` counterpart
 * so conversion needs zero re-keying. Deliberately no
 * immutable-after-create guard, unlike `Student` — an application
 * genuinely mutates through a long pipeline (offer, acceptance,
 * deposit, conversion), and each Action in that pipeline is
 * responsible for only setting the columns its own transition owns.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $intake_id
 * @property string|null $application_number
 * @property string $first_name
 * @property string|null $middle_names
 * @property string $last_name
 * @property Carbon $date_of_birth
 * @property string $gender
 * @property string $nationality
 * @property string|null $national_registration_no
 * @property string|null $national_registration_no_hash
 * @property string|null $birth_certificate_no
 * @property string|null $birth_certificate_no_hash
 * @property int $requested_grade_level_id
 * @property string $requested_enrolment_type
 * @property string $requested_residency
 * @property string|null $requested_pathway
 * @property array<int, int>|null $requested_subjects
 * @property bool $has_sibling_at_school
 * @property int|null $sibling_student_id
 * @property bool $guardian_is_alumnus
 * @property bool $guardian_is_staff
 * @property string|null $priority_score
 * @property string $status
 * @property int|null $application_fee_receipt_id
 * @property int|null $deposit_receipt_id
 * @property Carbon|null $offer_made_at
 * @property Carbon|null $offer_expires_at
 * @property Carbon|null $accepted_at
 * @property string|null $declined_reason
 * @property int|null $waitlist_position
 * @property int|null $student_id
 * @property Carbon|null $converted_at
 * @property Carbon|null $submitted_at
 * @property int|null $created_by
 * @property-read Collection<int, ApplicationGuardian> $guardians
 */
class Application extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'intake_id', 'application_number', 'first_name', 'middle_names',
        'last_name', 'date_of_birth', 'gender', 'nationality', 'national_registration_no',
        'national_registration_no_hash', 'birth_certificate_no', 'birth_certificate_no_hash',
        'home_language', 'religion', 'address_line_1', 'city', 'province',
        'requested_grade_level_id', 'requested_enrolment_type', 'requested_residency',
        'requested_pathway', 'requested_subjects', 'previous_school', 'previous_grade',
        'previous_results', 'has_sibling_at_school', 'sibling_student_id',
        'guardian_is_alumnus', 'guardian_is_staff', 'priority_score', 'status',
        'application_fee_receipt_id', 'deposit_receipt_id', 'offer_made_at',
        'offer_expires_at', 'accepted_at', 'declined_reason', 'waitlist_position',
        'student_id', 'converted_at', 'submitted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'national_registration_no' => 'encrypted',
            'birth_certificate_no' => 'encrypted',
            'requested_subjects' => 'array',
            'previous_results' => 'array',
            'has_sibling_at_school' => 'boolean',
            'guardian_is_alumnus' => 'boolean',
            'guardian_is_staff' => 'boolean',
            'priority_score' => 'decimal:2',
            'offer_made_at' => 'datetime',
            'offer_expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'converted_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ApplicationFactory::new();
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * @return BelongsTo<Intake, $this>
     */
    public function intake(): BelongsTo
    {
        return $this->belongsTo(Intake::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function requestedGradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'requested_grade_level_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<ApplicationGuardian, $this>
     */
    public function guardians(): HasMany
    {
        return $this->hasMany(ApplicationGuardian::class);
    }
}
