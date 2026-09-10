<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\MealAttendance;
use Modules\Boarding\Models\MealService;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<MealAttendance>
 */
class MealAttendanceFactory extends Factory
{
    protected $model = MealAttendance::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'meal_service_id' => MealService::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'attended' => true,
            'special_meal_served' => false,
            'recorded_at' => now(),
            'method' => 'manual',
        ];
    }
}
