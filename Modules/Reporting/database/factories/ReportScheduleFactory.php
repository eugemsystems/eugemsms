<?php

declare(strict_types=1);

namespace Modules\Reporting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportSchedule;

/**
 * @extends Factory<ReportSchedule>
 */
class ReportScheduleFactory extends Factory
{
    protected $model = ReportSchedule::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'report_definition_id' => fn (array $attributes): int => ReportDefinition::factory()->create(['school_id' => $attributes['school_id']])->id,
            'name' => 'Monthly income statement',
            'frequency' => 'monthly',
            'recipients' => [],
            'format' => 'pdf',
            'is_active' => true,
        ];
    }
}
