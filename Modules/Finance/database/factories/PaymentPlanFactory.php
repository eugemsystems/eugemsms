<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\PaymentPlan;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * @extends Factory<PaymentPlan>
 */
class PaymentPlanFactory extends Factory
{
    protected $model = PaymentPlan::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'party_type' => 'guardian',
            'party_id' => Guardian::factory()->for($school),
            'total_minor' => 30000,
            'currency' => 'USD',
            'instalment_count' => 3,
            'status' => 'proposed',
            'created_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
