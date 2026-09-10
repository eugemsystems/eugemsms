<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\MenuCycle;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

/**
 * @extends Factory<MenuCycle>
 */
class MenuCycleFactory extends Factory
{
    protected $model = MenuCycle::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'academic_year_id' => AcademicYear::factory()->for($school),
            'name' => 'Standard Weekly Cycle',
            'cycle_length_days' => 7,
            'is_active' => true,
        ];
    }
}
