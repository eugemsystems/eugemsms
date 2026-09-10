<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\DutyAssignment;
use Modules\People\Models\DutyRoster;
use Modules\People\Models\Staff;

/**
 * @extends Factory<DutyAssignment>
 */
class DutyAssignmentFactory extends Factory
{
    protected $model = DutyAssignment::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'roster_id' => DutyRoster::factory()->for($school),
            'staff_id' => Staff::factory()->for($school),
            'starts_at' => now(),
            'ends_at' => now()->addWeek(),
            'status' => 'assigned',
        ];
    }
}
