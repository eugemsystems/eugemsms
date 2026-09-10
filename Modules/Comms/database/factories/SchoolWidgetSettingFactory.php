<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\DashboardWidget;
use Modules\Comms\Models\SchoolWidgetSetting;
use Modules\Core\Models\School;

/**
 * @extends Factory<SchoolWidgetSetting>
 */
class SchoolWidgetSettingFactory extends Factory
{
    protected $model = SchoolWidgetSetting::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'widget_key' => fn (): string => DashboardWidget::factory()->create()->key,
            'persona' => 'parent',
            'is_enabled' => true,
            'sort_order' => 0,
        ];
    }
}
