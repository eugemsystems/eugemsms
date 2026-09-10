<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\People\Models\LeaveBalance;
use Modules\People\Models\LeaveType;
use Modules\People\Models\Staff;

/**
 * @extends Factory<LeaveBalance>
 */
class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'staff_id' => Staff::factory()->for($school),
            'leave_type_id' => LeaveType::factory()->for($school),
            'academic_year_id' => AcademicYear::factory()->for($school),
            'entitlement_days' => 21,
            'available_days' => 21,
        ];
    }
}
