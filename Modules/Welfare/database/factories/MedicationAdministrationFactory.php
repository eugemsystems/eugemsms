<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Models\MedicationAdministration;

/**
 * @extends Factory<MedicationAdministration>
 */
class MedicationAdministrationFactory extends Factory
{
    protected $model = MedicationAdministration::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'medication_name' => 'Paracetamol',
            'dose' => '500mg',
            'route' => 'oral',
            'administered_at' => now(),
            'administered_by' => User::factory(),
            'outcome' => 'given',
        ];
    }
}
