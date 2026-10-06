<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Sponsorship;
use Modules\People\Models\SponsorshipBeneficiary;
use Modules\People\Models\Student;

/**
 * @extends Factory<SponsorshipBeneficiary>
 */
class SponsorshipBeneficiaryFactory extends Factory
{
    protected $model = SponsorshipBeneficiary::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'sponsorship_id' => Sponsorship::factory()->for($school), 'student_id' => Student::factory()->for($school), 'starts_on' => now()->toDateString(), 'status' => 'active',
        ];
    }
}
