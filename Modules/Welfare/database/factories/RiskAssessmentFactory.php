<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Welfare\Models\RiskAssessment;
use Modules\Welfare\Models\SafeguardingCase;

/**
 * @extends Factory<RiskAssessment>
 */
class RiskAssessmentFactory extends Factory
{
    protected $model = RiskAssessment::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'case_id' => SafeguardingCase::factory()->create(['school_id' => $school]),
            'assessed_at' => now(),
            'assessed_by' => User::factory(),
            'risk_factors' => json_encode(['home_circumstances'], JSON_THROW_ON_ERROR),
            'risk_level' => 'medium',
            'rationale' => 'Based on the pattern of concerns raised this term.',
            'mitigation_plan' => 'Weekly check-ins with the pastoral team.',
            'review_due_on' => now()->addDays(30)->toDateString(),
        ];
    }
}
