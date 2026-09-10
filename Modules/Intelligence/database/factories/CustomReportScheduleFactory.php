<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\CustomReport;
use Modules\Intelligence\Models\CustomReportSchedule;

/**
 * @extends Factory<CustomReportSchedule>
 */
class CustomReportScheduleFactory extends Factory
{
    protected $model = CustomReportSchedule::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'report_id' => CustomReport::factory()->for($school),
            'frequency' => 'weekly',
            'recipients' => [['type' => 'user', 'channel' => 'email']],
            'format' => 'pdf',
            'next_run_at' => now()->addWeek(),
            'is_active' => true,
        ];
    }
}
