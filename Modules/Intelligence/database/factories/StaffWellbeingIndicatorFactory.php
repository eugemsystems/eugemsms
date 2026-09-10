<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Intelligence\Models\StaffWellbeingIndicator;
use Modules\People\Models\Staff;

/**
 * @extends Factory<StaffWellbeingIndicator>
 */
class StaffWellbeingIndicatorFactory extends Factory
{
    protected $model = StaffWellbeingIndicator::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'staff_id' => Staff::factory()->for($school),
            'term_id' => Term::factory()->for($school),
            'workload_utilisation_percent' => 90,
            'consecutive_terms_over_ceiling' => 0,
            'sick_leave_days_trend' => 'stable',
            'flag_level' => 'none',
        ];
    }
}
