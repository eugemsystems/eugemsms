<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\People\Models\Application;
use Modules\People\Models\Intake;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        $school = School::factory()->create();

        return [
            'school_id' => $school->id,
            'intake_id' => Intake::factory()->create(['school_id' => $school->id]),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-14 years', '-11 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'requested_grade_level_id' => GradeLevel::factory()->for($school)->create()->id,
            'requested_enrolment_type' => 'FULL_TIME',
            'requested_residency' => 'DAY',
            'status' => 'draft',
        ];
    }
}
