<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Models\Prescription;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'medication_name' => 'Amoxicillin',
            'dose' => '250mg',
            'frequency' => 'three times daily',
            'route' => 'oral',
            'prescribed_by' => 'Dr. T. Moyo',
            'prescribed_on' => now()->toDateString(),
            'starts_on' => now()->toDateString(),
            'status' => 'active',
        ];
    }
}
