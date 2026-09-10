<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Welfare\Models\ClinicObservation;
use Modules\Welfare\Models\SickBayAdmission;

/**
 * @extends Factory<ClinicObservation>
 */
class ClinicObservationFactory extends Factory
{
    protected $model = ClinicObservation::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'admission_id' => SickBayAdmission::factory()->create(['school_id' => $school]),
            'observed_at' => now(),
            'temperature_c' => 37.2,
            'pulse_bpm' => 78,
            'observed_by' => User::factory(),
        ];
    }
}
