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
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\ExternalReferralFactory;

/**
 * Book G BRD-06 §2/BR-BRD-06-017 ⭐ — closes the `hospital` roll-status
 * stub `BRD-02` §4 left open (`status` other than `returned` is what
 * `BRD-02` queries). `outcome` is `SecondaryEncrypted`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property int|null $admission_id
 * @property int|null $incident_id
 * @property string $referral_type
 * @property string $facility_name
 * @property string $reason
 * @property string $urgency
 * @property Carbon $referred_at
 * @property int $referred_by
 * @property string|null $transport_method
 * @property int|null $transport_request_id
 * @property int|null $escort_staff_id
 * @property Carbon|null $guardian_notified_at
 * @property bool|null $guardian_present
 * @property string|null $consent_reference
 * @property Carbon|null $departed_at
 * @property Carbon|null $returned_at
 * @property string|null $outcome
 * @property int|null $cost_minor
 * @property string|null $cost_borne_by
 * @property int|null $ad_hoc_charge_id
 * @property string $status
 */
class ExternalReferral extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ExternalReferralFactory> */
    use HasFactory;

    use HasUlid;

    public const array CURRENTLY_AWAY_STATUSES = ['referred', 'in_transit', 'at_facility', 'admitted'];

    protected $fillable = [
        'school_id', 'student_id', 'admission_id', 'incident_id', 'referral_type', 'facility_name',
        'reason', 'urgency', 'referred_at', 'referred_by', 'transport_method', 'transport_request_id',
        'escort_staff_id', 'guardian_notified_at', 'guardian_present', 'consent_reference',
        'departed_at', 'returned_at', 'outcome', 'cost_minor', 'cost_borne_by', 'ad_hoc_charge_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'referred_at' => 'datetime',
            'guardian_notified_at' => 'datetime',
            'guardian_present' => 'boolean',
            'departed_at' => 'datetime',
            'returned_at' => 'datetime',
            'outcome' => SecondaryEncrypted::class,
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExternalReferralFactory::new();
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
    public function escortStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'escort_staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }
}
