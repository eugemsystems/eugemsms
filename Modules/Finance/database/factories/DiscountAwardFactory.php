<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\AcademicYear;
use Modules\Finance\Models\DiscountAward;
use Modules\Finance\Models\DiscountScheme;
use Modules\People\Models\Student;

/**
 * @extends Factory<DiscountAward>
 */
class DiscountAwardFactory extends Factory
{
    protected $model = DiscountAward::class;

    public function definition(): array
    {
        return [
            'scheme_id' => DiscountScheme::factory(),
            'student_id' => Student::factory(),
            'application_id' => null,
            'academic_year_id' => AcademicYear::factory(),
            'term_id' => null,
            'applies_to_components' => null,
            'award_method' => 'percentage',
            'award_percent' => '10.00',
            'award_amount_minor' => null,
            'currency' => 'USD',
            'sponsor_guardian_id' => null,
            'effective_from' => Carbon::today()->toDateString(),
            'effective_to' => null,
            'status' => 'active',
            'condition_note' => null,
            'condition_last_checked_at' => null,
            'condition_met' => null,
            'granted_by' => User::factory(),
            'approval_request_id' => null,
            'revoked_reason' => null,
        ];
    }

    public function status(string $status): self
    {
        return $this->state(['status' => $status]);
    }

    public function fixedAmount(int $amountMinor): self
    {
        return $this->state(['award_method' => 'fixed_amount', 'award_amount_minor' => $amountMinor, 'award_percent' => null]);
    }

    public function sponsorFunded(int $sponsorGuardianId): self
    {
        return $this->state(['sponsor_guardian_id' => $sponsorGuardianId]);
    }
}
