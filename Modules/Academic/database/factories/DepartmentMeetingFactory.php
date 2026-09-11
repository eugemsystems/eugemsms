<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\DepartmentMeeting;
use Modules\Core\Models\School;
use Modules\People\Models\Department;
use Modules\People\Models\Staff;

/**
 * @extends Factory<DepartmentMeeting>
 */
class DepartmentMeetingFactory extends Factory
{
    protected $model = DepartmentMeeting::class;

    public function definition(): array
    {
        $school = School::factory();
        $chair = Staff::factory()->for($school);

        return [
            'school_id' => $school,
            'department_id' => Department::factory()->for($school),
            'meeting_date' => now()->toDateString(),
            'attendee_staff_ids' => [],
            'minutes' => 'Standard termly department meeting minutes.',
            'chaired_by' => $chair,
        ];
    }
}
