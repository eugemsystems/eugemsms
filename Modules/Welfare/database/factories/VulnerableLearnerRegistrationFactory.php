<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Models\VulnerableLearnerRegistration;

/**
 * @extends Factory<VulnerableLearnerRegistration>
 */
class VulnerableLearnerRegistrationFactory extends Factory
{
    protected $model = VulnerableLearnerRegistration::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'vulnerability_type' => 'bereavement',
            'identified_at' => now(),
            'identified_by' => User::factory(),
            'review_frequency_days' => 30,
            'next_review_on' => now()->addDays(30)->toDateString(),
            'status' => 'active',
        ];
    }
}
