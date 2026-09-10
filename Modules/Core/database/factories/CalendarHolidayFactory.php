<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\CalendarHoliday;
use Modules\Core\Models\School;

/**
 * @extends Factory<CalendarHoliday>
 */
class CalendarHolidayFactory extends Factory
{
    protected $model = CalendarHoliday::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Public Holiday',
            'starts_on' => now(),
            'ends_on' => now(),
            'type' => 'public',
        ];
    }
}
