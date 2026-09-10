<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffWorkload;

/**
 * @extends Factory<StaffWorkload>
 */
class StaffWorkloadFactory extends Factory
{
    protected $model = StaffWorkload::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'staff_id' => Staff::factory()->for($school),
            'term_id' => Term::factory()->for($school),
            'recalculated_at' => now(),
        ];
    }
}
