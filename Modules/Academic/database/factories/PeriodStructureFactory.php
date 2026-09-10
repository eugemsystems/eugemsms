<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\PeriodStructure;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

/**
 * @extends Factory<PeriodStructure>
 */
class PeriodStructureFactory extends Factory
{
    protected $model = PeriodStructure::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'academic_year_id' => AcademicYear::factory()->for($school),
            'name' => 'Secondary Day',
            'cycle_type' => 'weekly',
            'cycle_days' => 5,
            'day_labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
