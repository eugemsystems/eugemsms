<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\SchoolClassFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-02 §2 — a stream, e.g. 'Form 3 Blue'. BR-CORE-02-004: a
 * class always belongs to exactly one academic year; classes are created
 * fresh each year, never carried across.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $grade_level_id
 * @property string $code
 * @property string $name
 * @property string|null $stream_label
 * @property int|null $class_teacher_id
 * @property int|null $assistant_teacher_id
 * @property int|null $room_id
 * @property int $capacity
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AcademicYear $academicYear
 * @property-read GradeLevel $gradeLevel
 * @property-read User|null $classTeacher
 * @property-read User|null $assistantTeacher
 */
class SchoolClass extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'school_classes';

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'grade_level_id',
        'code',
        'name',
        'stream_label',
        'class_teacher_id',
        'assistant_teacher_id',
        'room_id',
        'capacity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SchoolClassFactory::new();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assistantTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assistant_teacher_id');
    }
}
