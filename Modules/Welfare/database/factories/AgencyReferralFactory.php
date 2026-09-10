<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Welfare\Models\AgencyReferral;
use Modules\Welfare\Models\SafeguardingCase;

/**
 * @extends Factory<AgencyReferral>
 */
class AgencyReferralFactory extends Factory
{
    protected $model = AgencyReferral::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'case_id' => SafeguardingCase::factory()->create(['school_id' => $school]),
            'agency_type' => 'social_services',
            'agency_name' => 'Department of Social Welfare',
            'referred_at' => now(),
            'referred_by' => User::factory(),
            'reason' => 'Statutory referral following an ongoing concern.',
            'consent_basis' => 'legal_obligation',
            'status' => 'made',
        ];
    }
}
