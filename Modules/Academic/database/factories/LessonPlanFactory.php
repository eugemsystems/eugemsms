<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LessonPlan;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * @extends Factory<LessonPlan>
 */
class LessonPlanFactory extends Factory
{
    protected $model = LessonPlan::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'teacher_staff_id' => Staff::factory()->for($school),
            'lesson_date' => now()->toDateString(),
            'topic' => 'Introduction to Forces',
            'status' => 'draft',
        ];
    }
}
