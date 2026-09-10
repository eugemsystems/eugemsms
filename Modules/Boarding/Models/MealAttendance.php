<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\MealAttendanceFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Student;

/**
 * Book F BRD-04 §2/BR-BRD-04-015.
 *
 * @property int $id
 * @property int $school_id
 * @property int $meal_service_id
 * @property int $student_id
 * @property bool $attended
 * @property bool $special_meal_served
 * @property Carbon $recorded_at
 * @property string $method
 */
class MealAttendance extends Model
{
    protected $table = 'meal_attendance';

    use BelongsToSchool;

    /** @use HasFactory<MealAttendanceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'meal_service_id', 'student_id', 'attended', 'special_meal_served', 'recorded_at', 'method'];

    protected function casts(): array
    {
        return [
            'attended' => 'boolean',
            'special_meal_served' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MealAttendanceFactory::new();
    }

    /**
     * @return BelongsTo<MealService, $this>
     */
    public function mealService(): BelongsTo
    {
        return $this->belongsTo(MealService::class, 'meal_service_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
