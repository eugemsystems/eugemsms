<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\MealService;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<MealService>
 */
class MealServiceFactory extends Factory
{
    protected $model = MealService::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'service_date' => now()->toDateString(),
            'meal' => 'lunch',
            'currency' => 'USD',
            'status' => 'planned',
        ];
    }
}
