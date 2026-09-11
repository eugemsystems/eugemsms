<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\People\Models\Alumnus;
use Modules\People\Models\Student;

/**
 * @extends Factory<Alumnus>
 */
class AlumnusFactory extends Factory
{
    protected $model = Alumnus::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'admission_number' => 'ADM/'.$this->faker->unique()->numerify('######'),
            'graduation_year' => (int) now()->year,
            'final_grade_level_id' => GradeLevel::factory()->for($school),
            'academic_summary_snapshot' => ['terms' => [], 'final_average_percent' => null, 'honours' => []],
            'is_notable' => false,
            'status' => 'active',
        ];
    }
}
