<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Models\ExternalReferral;

/**
 * @extends Factory<ExternalReferral>
 */
class ExternalReferralFactory extends Factory
{
    protected $model = ExternalReferral::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'referral_type' => 'hospital',
            'facility_name' => 'Parirenyatwa Group of Hospitals',
            'reason' => 'Suspected fracture, requires X-ray.',
            'urgency' => 'urgent',
            'referred_at' => now(),
            'referred_by' => User::factory(),
            'status' => 'referred',
        ];
    }
}
