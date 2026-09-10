<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Stores\Models\StockTake;
use Modules\Stores\Models\Store;

/**
 * @extends Factory<StockTake>
 */
class StockTakeFactory extends Factory
{
    protected $model = StockTake::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'store_id' => Store::factory()->for($school),
            'take_number' => 'ST/'.fake()->unique()->numberBetween(1000, 99999),
            'take_type' => 'full',
            'scheduled_for' => now()->toDateString(),
            'status' => 'planned',
            'is_blind_count' => true,
        ];
    }
}
