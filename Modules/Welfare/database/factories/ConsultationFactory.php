<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Models\Consultation;

/**
 * @extends Factory<Consultation>
 */
class ConsultationFactory extends Factory
{
    protected $model = Consultation::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'consulted_at' => now(),
            'consultation_type' => 'walk_in',
            'presenting_complaint' => 'Sore throat for two days.',
            'practitioner_type' => 'nurse',
        ];
    }
}
