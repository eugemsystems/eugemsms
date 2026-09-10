<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TeachingGroup;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<TeachingGroup>
 */
class TeachingGroupFactory extends Factory
{
    protected $model = TeachingGroup::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'subject_id' => Subject::factory()->for($school),
            'grade_level_id' => GradeLevel::factory()->for($school),
            'code' => 'SET-'.$this->faker->unique()->numberBetween(1, 999999),
            'name' => 'Set '.$this->faker->word(),
            'current_count' => 0,
            'is_active' => true,
        ];
    }
}
