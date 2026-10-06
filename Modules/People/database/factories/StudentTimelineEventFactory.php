<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\People\Models\StudentTimelineEvent;

/**
 * @extends Factory<StudentTimelineEvent>
 */
class StudentTimelineEventFactory extends Factory
{
    protected $model = StudentTimelineEvent::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school), 'event_category' => 'administrative', 'event_type' => 'note', 'title' => 'Note', 'occurred_at' => now(),
        ];
    }
}
