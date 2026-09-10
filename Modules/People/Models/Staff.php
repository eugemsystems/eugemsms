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
use Modules\People\Database\Factories\StaffFactory;

/**
 * Book C PPL-04 §2. The master staff record. `staff_qualifications`
 * and `staff_documents` are deferred — see this module's scope note.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $user_id
 * @property string $staff_number
 * @property string|null $title
 * @property string $first_name
 * @property string|null $middle_names
 * @property string $last_name
 * @property string|null $preferred_name
 * @property Carbon $date_of_birth
 * @property string $gender
 * @property string $nationality
 * @property string|null $national_registration_no
 * @property string|null $passport_no
 * @property string|null $marital_status
 * @property int|null $photo_file_id
 * @property string $primary_phone
 * @property string|null $alternate_phone
 * @property string|null $personal_email
 * @property string|null $work_email
 * @property string $staff_category
 * @property int|null $department_id
 * @property int|null $post_id
 * @property int|null $reports_to_staff_id
 * @property Carbon $joined_on
 * @property Carbon|null $confirmed_on
 * @property Carbon|null $exited_on
 * @property string|null $exit_reason
 * @property string $status
 * @property string|null $zimra_bp_number
 * @property string|null $nssa_number
 * @property string|null $bank_account_number
 * @property bool $is_teaching
 * @property string|null $teacher_registration_no
 * @property int|null $max_weekly_periods
 * @property int|null $created_by
 */
class Staff extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffFactory> */
    use HasFactory;

    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'user_id', 'staff_number', 'title', 'first_name', 'middle_names', 'last_name',
        'preferred_name', 'date_of_birth', 'gender', 'nationality', 'national_registration_no',
        'passport_no', 'marital_status', 'photo_file_id', 'primary_phone', 'alternate_phone',
        'personal_email', 'work_email', 'address_line_1', 'city', 'province', 'kin_name',
        'kin_relationship', 'kin_phone', 'kin_address', 'staff_category', 'department_id', 'post_id',
        'reports_to_staff_id', 'joined_on', 'confirmed_on', 'exited_on', 'exit_reason', 'status',
        'zimra_bp_number', 'nssa_number', 'nec_membership_number', 'pension_scheme',
        'medical_aid_provider', 'medical_aid_number', 'bank_name', 'bank_branch', 'bank_account_number',
        'bank_account_currency', 'is_teaching', 'teacher_registration_no', 'max_weekly_periods', 'notes',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'national_registration_no' => 'encrypted',
            'passport_no' => 'encrypted',
            'joined_on' => 'date',
            'confirmed_on' => 'date',
            'exited_on' => 'date',
            'zimra_bp_number' => 'encrypted',
            'nssa_number' => 'encrypted',
            'medical_aid_number' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'is_teaching' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StaffFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            if (in_array('staff_number', array_keys($model->getDirty()), true)) {
                throw new InvalidStateTransitionException(
                    'staff_number is immutable once assigned (BR-PPL-04-001).',
                    ['dirty' => ['staff_number']],
                );
            }
        });
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<EstablishmentPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(EstablishmentPost::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'reports_to_staff_id');
    }

    /**
     * @return HasMany<StaffContract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(StaffContract::class);
    }

    /**
     * @return HasMany<TeacherAllocation, $this>
     */
    public function teacherAllocations(): HasMany
    {
        return $this->hasMany(TeacherAllocation::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return HasMany<LeaveBalance, $this>
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function activeContract(): ?StaffContract
    {
        return $this->contracts()->where('status', 'active')->first();
    }
}
