<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\Welfare\Models\MedicalConsent;

/**
 * @extends Factory<MedicalConsent>
 */
class MedicalConsentFactory extends Factory
{
    protected $model = MedicalConsent::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'guardian_id' => Guardian::factory()->for($school),
            'consent_type' => 'prescribed_medication',
            'granted' => true,
            'granted_at' => now(),
            'granted_via' => 'form',
            'effective_from' => now()->toDateString(),
        ];
    }
}
