<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Models\Immunisation;

/**
 * @extends Factory<Immunisation>
 */
class ImmunisationFactory extends Factory
{
    protected $model = Immunisation::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'vaccine' => 'Tetanus',
            'dose_number' => 1,
            'administered_on' => now()->toDateString(),
            'status' => 'recorded',
        ];
    }
}
