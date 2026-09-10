<?php

declare(strict_types=1);

namespace Modules\People\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\House;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolSection;
use Modules\People\Database\Factories\StudentFactory;
use ReflectionProperty;

/**
 * Book C PPL-01 §2. The master learner record.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $user_id
 * @property string $admission_number
 * @property string|null $former_admission_number
 * @property string $first_name
 * @property string|null $middle_names
 * @property string $last_name
 * @property string|null $preferred_name
 * @property Carbon $date_of_birth
 * @property string $gender
 * @property string $nationality
 * @property string|null $home_language
 * @property string|null $religion
 * @property string|null $national_registration_no
 * @property string|null $national_registration_no_hash
 * @property string|null $birth_certificate_no
 * @property string|null $birth_certificate_no_hash
 * @property string|null $passport_no
 * @property int|null $photo_file_id
 * @property string $enrolment_type
 * @property string $residency
 * @property int $section_id
 * @property int $grade_level_id
 * @property int|null $class_id
 * @property int|null $house_id
 * @property string|null $pathway
 * @property int $entry_cohort_year
 * @property string $status
 * @property string|null $status_reason_code
 * @property Carbon|null $status_changed_at
 * @property int|null $status_changed_by
 * @property Carbon|null $enrolled_on
 * @property Carbon|null $exited_on
 * @property bool $has_medical_alert
 * @property bool $has_allergy_alert
 * @property bool $has_dietary_requirement
 * @property bool $has_sen_record
 * @property bool $has_safeguarding_flag
 * @property bool $is_vulnerable
 * @property string|null $rfid_tag
 */
class Student extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    use HasUlid;
    use SoftDeletes;

    /**
     * BR-PPL-01-004: every change to these columns must go through
     * `ACT-ChangeBillingAttribute`. A real class property (not an
     * Eloquent attribute), the same shape as
     * `BelongsToSession::$periodOverrideApproved` — that action sets it
     * to `true` immediately before `save()`, and nothing else may.
     */
    public bool $billingAttributeChangeAuthorized = false;

    /**
     * BR-PPL-01-011: status may only change through
     * `ACT-ChangeStudentStatus`, which enforces the state machine.
     */
    public bool $statusChangeAuthorized = false;

    /**
     * @var array<int, string>
     */
    private const array BILLING_ATTRIBUTES = [
        'enrolment_type', 'residency', 'grade_level_id', 'class_id', 'section_id', 'pathway',
    ];

    protected $fillable = [
        'school_id', 'user_id', 'admission_number', 'former_admission_number',
        'first_name', 'middle_names', 'last_name', 'preferred_name', 'date_of_birth',
        'gender', 'nationality', 'home_language', 'religion', 'national_registration_no',
        'national_registration_no_hash', 'birth_certificate_no', 'birth_certificate_no_hash',
        'passport_no', 'photo_file_id', 'enrolment_type', 'residency', 'section_id',
        'grade_level_id', 'class_id', 'house_id', 'pathway', 'entry_cohort_year', 'status',
        'status_reason_code', 'status_changed_at', 'status_changed_by', 'enrolled_on',
        'exited_on', 'address_line_1', 'address_line_2', 'suburb', 'city', 'province',
        'latitude', 'longitude', 'transport_zone_id', 'has_medical_alert', 'has_allergy_alert',
        'has_dietary_requirement', 'has_sen_record', 'has_safeguarding_flag', 'is_vulnerable',
        'blood_group', 'rfid_tag', 'biometric_reference', 'id_card_issued_at', 'notes',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'national_registration_no' => 'encrypted',
            'birth_certificate_no' => 'encrypted',
            'passport_no' => 'encrypted',
            'status_changed_at' => 'datetime',
            'enrolled_on' => 'date',
            'exited_on' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'has_medical_alert' => 'boolean',
            'has_allergy_alert' => 'boolean',
            'has_dietary_requirement' => 'boolean',
            'has_sen_record' => 'boolean',
            'has_safeguarding_flag' => 'boolean',
            'is_vulnerable' => 'boolean',
            'id_card_issued_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StudentFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $dirtyBilling = array_intersect($dirty, self::BILLING_ATTRIBUTES);

            if ($dirtyBilling !== [] && ! self::isBillingChangeAuthorized($model)) {
                throw new InvalidStateTransitionException(
                    'Billing attributes (enrolment_type, residency, grade_level_id, class_id, section_id, pathway) can only change through ACT-ChangeBillingAttribute.',
                    ['dirty' => $dirtyBilling],
                );
            }

            if (in_array('status', $dirty, true) && ! self::isStatusChangeAuthorized($model)) {
                throw new InvalidStateTransitionException(
                    'status can only change through ACT-ChangeStudentStatus.',
                    ['dirty' => ['status']],
                );
            }

            if (in_array('admission_number', $dirty, true)) {
                throw new InvalidStateTransitionException(
                    'admission_number is immutable once assigned (BR-PPL-01-001).',
                    ['dirty' => ['admission_number']],
                );
            }
        });
    }

    private static function isBillingChangeAuthorized(Model $model): bool
    {
        return property_exists($model, 'billingAttributeChangeAuthorized')
            && (new ReflectionProperty($model, 'billingAttributeChangeAuthorized'))->getValue($model) === true;
    }

    private static function isStatusChangeAuthorized(Model $model): bool
    {
        return property_exists($model, 'statusChangeAuthorized')
            && (new ReflectionProperty($model, 'statusChangeAuthorized'))->getValue($model) === true;
    }

    /**
     * @return BelongsTo<SchoolSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<House, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<StudentEnrolment, $this>
     */
    public function enrolments(): HasMany
    {
        return $this->hasMany(StudentEnrolment::class);
    }

    /**
     * @return HasMany<StudentAttributeChange, $this>
     */
    public function attributeChanges(): HasMany
    {
        return $this->hasMany(StudentAttributeChange::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
