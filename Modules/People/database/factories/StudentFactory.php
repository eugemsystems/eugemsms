<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\People\Models\Student;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'admission_number' => 'ADM/'.fake()->unique()->numerify('######'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-18 years', '-5 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'nationality' => 'ZW',
            'enrolment_type' => 'FULL_TIME',
            'residency' => 'DAY',
            'section_id' => SchoolSection::factory(),
            'grade_level_id' => GradeLevel::factory(),
            'entry_cohort_year' => (int) now()->year,
            'status' => 'active',
            'enrolled_on' => now()->toDateString(),
        ];
    }

    public function boarder(): self
    {
        return $this->state(fn (): array => ['residency' => 'BOARDER']);
    }

    public function partTime(): self
    {
        return $this->state(fn (): array => ['enrolment_type' => 'PART_TIME']);
    }

    public function withdrawn(): self
    {
        return $this->state(fn (): array => ['status' => 'withdrawn', 'exited_on' => now()->toDateString()]);
    }
}
