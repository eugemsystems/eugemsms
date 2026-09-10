<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Academic\Database\Factories\TimetableConstraintFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book E ACA-03 §2/§4 ⭐/BR-ACA-03-004.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property string $constraint_type
 * @property string $severity
 * @property int $weight
 * @property int|null $subject_id
 * @property int|null $staff_id
 * @property int|null $class_id
 * @property int|null $venue_id
 * @property int|null $grade_level_id
 * @property array<int, int>|null $cycle_days
 * @property array<int, int>|null $period_numbers
 * @property int|null $value
 * @property string|null $reason
 * @property bool $is_active
 */
class TimetableConstraint extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TimetableConstraintFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'constraint_type', 'severity', 'weight', 'subject_id',
        'staff_id', 'class_id', 'venue_id', 'grade_level_id', 'cycle_days', 'period_numbers',
        'value', 'reason', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cycle_days' => 'array',
            'period_numbers' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TimetableConstraintFactory::new();
    }

    public function isHard(): bool
    {
        return $this->severity === 'hard';
    }
}
