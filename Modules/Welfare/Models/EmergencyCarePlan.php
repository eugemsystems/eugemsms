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
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\EmergencyCarePlanFactory;

/**
 * Book G BRD-06 §2/BR-BRD-06-007 — deliberately Tier 2, plain language,
 * no diagnosis. Every field on this model is safe for any staff
 * member with care responsibility to read.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property int|null $condition_id
 * @property string $title
 * @property string $trigger_signs
 * @property string $immediate_actions
 * @property string|null $medication_location
 * @property string|null $medication_name
 * @property string|null $do_not_do
 * @property string $who_to_call
 * @property Carbon|null $review_due_on
 * @property int|null $approved_by_nurse
 * @property Carbon|null $approved_at
 * @property bool $guardian_acknowledged
 * @property bool $is_active
 */
class EmergencyCarePlan extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EmergencyCarePlanFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'student_id', 'condition_id', 'title', 'trigger_signs', 'immediate_actions',
        'medication_location', 'medication_name', 'do_not_do', 'who_to_call', 'review_due_on',
        'approved_by_nurse', 'approved_at', 'guardian_acknowledged', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'review_due_on' => 'date',
            'approved_at' => 'datetime',
            'guardian_acknowledged' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EmergencyCarePlanFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<MedicalCondition, $this>
     */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(MedicalCondition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedByNurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_nurse');
    }
}
