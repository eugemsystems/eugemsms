<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\CalendarEvent;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'academic_year_id' => AcademicYear::factory()->for($school),
            'term_id' => null,
            'source_type' => 'manual',
            'source_module_id' => null,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->sentence(),
            'starts_at' => now()->addDays($this->faker->numberBetween(1, 30)),
            'ends_at' => null,
            'is_all_day' => false,
            'location' => null,
            'audience_scope' => 'whole_school',
            'audience_scope_id' => null,
            'colour' => '#3366ff',
            'is_public' => true,
            'rebuilt_at' => now(),
        ];
    }
}
