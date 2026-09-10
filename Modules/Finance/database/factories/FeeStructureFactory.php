<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Finance\Models\FeeStructure;

/**
 * @extends Factory<FeeStructure>
 */
class FeeStructureFactory extends Factory
{
    protected $model = FeeStructure::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'academic_year_id' => AcademicYear::factory()->for($school),
            'name' => fake()->words(3, true),
            'version' => 1,
            'status' => 'active',
            'priority' => 100,
        ];
    }
}
