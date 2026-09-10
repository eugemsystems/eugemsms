<?php

declare(strict_types=1);

namespace Modules\Sport\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Models\AdHocCharge;
use Modules\People\Models\Student;
use Modules\Sport\Database\Factories\ActivityMembershipFactory;

/**
 * Book H2 OPS-07 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $activity_id
 * @property int $student_id
 * @property string|null $role
 * @property Carbon $joined_on
 * @property Carbon|null $left_on
 * @property bool $consent_received
 * @property bool|null $medical_cleared
 * @property string $billing_status
 * @property int|null $ad_hoc_charge_id
 * @property string $status
 */
class ActivityMembership extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ActivityMembershipFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'activity_id', 'student_id', 'role',
        'joined_on', 'left_on', 'consent_received', 'medical_cleared', 'billing_status',
        'ad_hoc_charge_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'left_on' => 'date',
            'consent_received' => 'boolean',
            'medical_cleared' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ActivityMembershipFactory::new();
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<AdHocCharge, $this>
     */
    public function adHocCharge(): BelongsTo
    {
        return $this->belongsTo(AdHocCharge::class);
    }
}
