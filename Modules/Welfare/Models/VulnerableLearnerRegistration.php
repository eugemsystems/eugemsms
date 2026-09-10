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
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\VulnerableLearnerRegistrationFactory;

/**
 * Book G BRD-08 §2/BR-BRD-08-018. `support_plan` is `SecondaryEncrypted`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property string $vulnerability_type
 * @property Carbon $identified_at
 * @property int $identified_by
 * @property string|null $support_plan
 * @property int|null $assigned_mentor_id
 * @property int $review_frequency_days
 * @property Carbon|null $next_review_on
 * @property string $status
 */
class VulnerableLearnerRegistration extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<VulnerableLearnerRegistrationFactory> */
    use HasFactory;

    protected $table = 'vulnerable_learner_register';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'vulnerability_type', 'identified_at', 'identified_by', 'support_plan',
        'assigned_mentor_id', 'review_frequency_days', 'next_review_on', 'status',
    ];

    protected function casts(): array
    {
        return [
            'identified_at' => 'datetime',
            'support_plan' => SecondaryEncrypted::class,
            'next_review_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return VulnerableLearnerRegistrationFactory::new();
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
    public function assignedMentor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_mentor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function identifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identified_by');
    }
}
