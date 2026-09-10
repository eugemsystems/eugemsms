<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\LearnerIncompatibility;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<LearnerIncompatibility>
 */
class LearnerIncompatibilityFactory extends Factory
{
    protected $model = LearnerIncompatibility::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_a_id' => Student::factory()->for($school),
            'student_b_id' => Student::factory()->for($school),
            'scope' => 'room',
            'reason_category' => 'conflict',
            'reason' => 'Documented conflict between the two learners.',
            'is_confidential' => true,
            'raised_by' => User::factory(),
            'is_active' => true,
        ];
    }
}
