<?php

declare(strict_types=1);

namespace Modules\Sport\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Sport\Database\Factories\TeamFactory;

/**
 * Book H2 OPS-07 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $activity_id
 * @property string $name
 * @property string|null $age_group
 * @property string|null $level
 * @property int|null $coach_staff_id
 * @property int|null $captain_student_id
 * @property bool $is_active
 */
class Team extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'activity_id', 'name', 'age_group', 'level',
        'coach_staff_id', 'captain_student_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TeamFactory::new();
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'coach_staff_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function captain(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'captain_student_id');
    }
}
