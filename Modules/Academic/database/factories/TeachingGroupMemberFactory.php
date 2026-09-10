<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\TeachingGroup;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<TeachingGroupMember>
 */
class TeachingGroupMemberFactory extends Factory
{
    protected $model = TeachingGroupMember::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'teaching_group_id' => TeachingGroup::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'effective_from' => now()->toDateString(),
        ];
    }
}
