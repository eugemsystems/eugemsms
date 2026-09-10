<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\DietaryRequirementFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Student;

/**
 * Book F BRD-04 §2/BR-BRD-04-009/010/011 ⭐ — safeguarding, not
 * preference.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property string $requirement_type
 * @property string $severity
 * @property array<int, string>|null $allergens
 * @property array<int, string>|null $excluded_items
 * @property string $description
 * @property string|null $alternative_provision
 * @property int|null $medical_source_id
 * @property bool $requires_epipen
 * @property bool $verified_by_nurse
 * @property Carbon|null $verified_at
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property bool $is_active
 */
class DietaryRequirement extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DietaryRequirementFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'requirement_type', 'severity', 'allergens', 'excluded_items',
        'description', 'alternative_provision', 'medical_source_id', 'requires_epipen',
        'verified_by_nurse', 'verified_at', 'effective_from', 'effective_to', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allergens' => 'array',
            'excluded_items' => 'array',
            'requires_epipen' => 'boolean',
            'verified_by_nurse' => 'boolean',
            'verified_at' => 'datetime',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DietaryRequirementFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isClinical(): bool
    {
        return in_array($this->requirement_type, ['allergy', 'intolerance', 'medical'], true);
    }
}
