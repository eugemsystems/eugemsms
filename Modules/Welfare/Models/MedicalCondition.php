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
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\MedicalConditionFactory;

/**
 * Book G BRD-06 §2/§0.2 ⭐ — `public_summary`/`affects_*`/
 * `accommodation_requirement` are Tier 2; `name` and `diagnosis_notes`
 * are `SecondaryEncrypted` Tier 3.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property string $condition_type
 * @property string|null $category
 * @property string $name
 * @property string $severity
 * @property string|null $public_summary
 * @property bool $requires_emergency_plan
 * @property bool $affects_dietary
 * @property bool $affects_physical_activity
 * @property bool $affects_accommodation
 * @property string|null $accommodation_requirement
 * @property string|null $diagnosis_notes
 * @property Carbon|null $diagnosed_on
 * @property string|null $diagnosed_by
 * @property int|null $supporting_document_id
 * @property bool $verified_by_nurse
 * @property Carbon|null $verified_at
 * @property int|null $verified_by
 * @property string $status
 * @property string $declared_by
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property int|null $created_by
 */
class MedicalCondition extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MedicalConditionFactory> */
    use HasFactory;

    use HasUlid;

    public const array LIFE_THREATENING_SEVERITIES = ['life_threatening', 'severe'];

    protected $fillable = [
        'school_id', 'student_id', 'condition_type', 'category', 'name', 'severity', 'public_summary',
        'requires_emergency_plan', 'affects_dietary', 'affects_physical_activity', 'affects_accommodation',
        'accommodation_requirement', 'diagnosis_notes', 'diagnosed_on', 'diagnosed_by', 'supporting_document_id',
        'verified_by_nurse', 'verified_at', 'verified_by', 'status', 'declared_by', 'effective_from',
        'effective_to', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'name' => SecondaryEncrypted::class,
            'diagnosis_notes' => SecondaryEncrypted::class,
            'requires_emergency_plan' => 'boolean',
            'affects_dietary' => 'boolean',
            'affects_physical_activity' => 'boolean',
            'affects_accommodation' => 'boolean',
            'diagnosed_on' => 'date',
            'verified_by_nurse' => 'boolean',
            'verified_at' => 'datetime',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MedicalConditionFactory::new();
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
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
