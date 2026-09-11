<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\CourseSpace;
use Modules\Academic\Models\TeachingGroup;

/**
 * @extends Factory<CourseSpace>
 */
class CourseSpaceFactory extends Factory
{
    protected $model = CourseSpace::class;

    public function definition(): array
    {
        // The teaching group is created explicitly first, and every
        // other FK derived from it, so a course space never ends up
        // pointing at a different school/term/subject than the
        // teaching group it's supposed to be a 1:1 mirror of.
        $teachingGroup = TeachingGroup::factory()->create();

        return [
            'school_id' => $teachingGroup->school_id,
            'academic_year_id' => $teachingGroup->academic_year_id,
            'term_id' => $teachingGroup->term_id,
            'subject_id' => $teachingGroup->subject_id,
            'teaching_group_id' => $teachingGroup->id,
            'is_active' => true,
        ];
    }
}
