<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\LaundryCycle;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<LaundryCycle>
 */
class LaundryCycleFactory extends Factory
{
    protected $model = LaundryCycle::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'hostel_id' => Hostel::factory()->for($school),
            'cycle_date' => now()->toDateString(),
            'collected_at' => null,
            'returned_at' => null,
            'items_collected' => 0,
            'items_returned' => 0,
            'items_missing' => 0,
            'status' => 'scheduled',
            'cost_minor' => null,
            'supervised_by' => null,
        ];
    }
}
