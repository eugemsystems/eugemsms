<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\People\Models\Student;
use Modules\People\Models\StudentPriorResult;

/**
 * @extends Factory<StudentPriorResult>
 */
class StudentPriorResultFactory extends Factory
{
    protected $model = StudentPriorResult::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => function (array $attributes): int {
                $section = SchoolSection::factory()->create(['school_id' => $attributes['school_id']]);
                $gradeLevel = GradeLevel::factory()->create(['school_id' => $attributes['school_id'], 'section_id' => $section->id]);

                return Student::factory()->create([
                    'school_id' => $attributes['school_id'],
                    'section_id' => $section->id,
                    'grade_level_id' => $gradeLevel->id,
                ])->id;
            },
            'prior_school_id' => null,
            'examination' => 'ZIMSEC O-Level',
            'exam_year' => (int) now()->format('Y'),
            'candidate_number' => (string) $this->faker->numerify('##########'),
            'subject' => 'Mathematics',
            'grade' => 'B',
            'points' => null,
            'is_verified' => true,
        ];
    }
}
