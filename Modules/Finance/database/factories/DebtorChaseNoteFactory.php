<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\DebtorChaseNote;
use Modules\People\Models\Student;

/**
 * @extends Factory<DebtorChaseNote>
 */
class DebtorChaseNoteFactory extends Factory
{
    protected $model = DebtorChaseNote::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'outcome' => 'promised_to_pay',
            'note' => fake()->sentence(10),
            'recorded_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
