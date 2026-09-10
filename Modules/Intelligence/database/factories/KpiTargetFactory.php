<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\KpiTarget;

/**
 * @extends Factory<KpiTarget>
 */
class KpiTargetFactory extends Factory
{
    protected $model = KpiTarget::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'kpi_key' => 'collection_rate',
            'academic_year_id' => AcademicYear::factory()->for($school),
            'target_value' => 92,
            'warning_threshold_percent' => 90,
        ];
    }
}
