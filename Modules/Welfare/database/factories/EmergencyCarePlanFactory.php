<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Models\EmergencyCarePlan;

/**
 * @extends Factory<EmergencyCarePlan>
 */
class EmergencyCarePlanFactory extends Factory
{
    protected $model = EmergencyCarePlan::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'title' => 'Anaphylaxis response',
            'trigger_signs' => 'Swelling, hives, difficulty breathing.',
            'immediate_actions' => 'Administer EpiPen, call ambulance, notify guardian.',
            'medication_location' => 'Sanatorium fridge, shelf 2',
            'medication_name' => 'EpiPen',
            'who_to_call' => 'Sanatorium: 0242 000 000',
            'is_active' => true,
        ];
    }
}
