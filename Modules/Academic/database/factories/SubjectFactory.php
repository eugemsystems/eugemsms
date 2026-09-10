<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\Subject;
use Modules\Core\Models\School;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        $school = School::factory();
        $name = fake()->randomElement([
            'Combined Science', 'Mathematics', 'English Language', 'History',
            'Geography', 'Accounting', 'Commerce', 'Agriculture', 'Woodwork',
        ]);

        return [
            'school_id' => $school,
            'framework_id' => CurriculumFramework::factory()->for($school),
            'code' => strtoupper(str(fake()->unique()->bothify('SUB###'))->value()),
            'name' => $name,
            'short_name' => strtoupper(substr($name, 0, 8)),
            'subject_type' => 'elective',
            'is_examinable' => true,
            'requires_sbp' => true,
            'is_active' => true,
        ];
    }
}
