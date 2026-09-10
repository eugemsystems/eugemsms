<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\DashboardWidget;

/**
 * @extends Factory<DashboardWidget>
 */
class DashboardWidgetFactory extends Factory
{
    protected $model = DashboardWidget::class;

    public function definition(): array
    {
        return [
            'key' => 'test_widget_'.$this->faker->unique()->numerify('###'),
            'module_code' => 'FIN-03',
            'persona' => 'parent',
            'title' => 'Fee Balance',
            'data_endpoint' => '/api/v1/portal/parent/dashboard',
            'min_grade_ordinal' => null,
            'requires_module' => null,
            'default_enabled' => true,
            'default_sort_order' => 0,
        ];
    }
}
