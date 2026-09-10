<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffDisciplinaryCase;

/**
 * @extends Factory<StaffDisciplinaryCase>
 */
class StaffDisciplinaryCaseFactory extends Factory
{
    protected $model = StaffDisciplinaryCase::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'staff_id' => Staff::factory()->for($school),
            'case_number' => 'DISC/'.fake()->unique()->numerify('######'),
            'category' => 'conduct',
            'description' => fake()->sentence(),
            'incident_date' => now()->subDay()->toDateString(),
            'reported_by' => User::factory(),
            'stage' => 'reported',
            'is_confidential' => true,
            'created_at' => now(),
        ];
    }
}
