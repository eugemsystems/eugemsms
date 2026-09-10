<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\CollectionAttempt;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<CollectionAttempt>
 */
class CollectionAttemptFactory extends Factory
{
    protected $model = CollectionAttempt::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'attempted_by_name' => fake()->name(),
            'outcome' => 'released',
            'verified_by_photo' => false,
            'gate_staff_id' => User::factory(),
            'occurred_at' => now(),
        ];
    }
}
