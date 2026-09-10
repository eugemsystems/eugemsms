<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Compliance\Models\ZimsecResult;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<ZimsecResult>
 */
class ZimsecResultFactory extends Factory
{
    protected $model = ZimsecResult::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'registration_id' => fn (array $attributes): int => ZimsecRegistration::factory()->create(['school_id' => $attributes['school_id']])->id,
            'student_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'candidate_number' => (string) $this->faker->numerify('##########'),
            'subject_code' => '4028',
            'subject_name' => 'Mathematics',
            'grade' => 'B',
            'points' => null,
            'is_provisional' => false,
            'imported_at' => now(),
            'imported_by' => User::factory(),
            'source_file_id' => null,
        ];
    }
}
