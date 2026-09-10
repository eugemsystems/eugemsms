<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intelligence\Models\KpiDefinition;

/**
 * @extends Factory<KpiDefinition>
 */
class KpiDefinitionFactory extends Factory
{
    protected $model = KpiDefinition::class;

    public function definition(): array
    {
        return [
            'school_id' => null,
            'key' => 'test_kpi_'.$this->faker->unique()->numerify('###'),
            'module_code' => 'TEST',
            'label' => 'Test KPI',
            'unit' => 'percent',
            'data_source_endpoint' => '/api/v1/executive/kpis',
            'higher_is_better' => true,
            'default_target_value' => 90,
        ];
    }
}
