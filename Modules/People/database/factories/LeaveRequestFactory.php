<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\LeaveType;
use Modules\People\Models\Staff;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'staff_id' => Staff::factory()->for($school),
            'leave_type_id' => LeaveType::factory()->for($school),
            'academic_year_id' => AcademicYear::factory()->for($school),
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addWeek()->addDays(2)->toDateString(),
            'working_days' => 3,
            'status' => 'pending',
            'created_at' => now(),
        ];
    }
}
