<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Household;
use Modules\People\Models\HouseholdMember;
use Modules\People\Models\Student;

/**
 * @extends Factory<HouseholdMember>
 */
class HouseholdMemberFactory extends Factory
{
    protected $model = HouseholdMember::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'household_id' => Household::factory()->for($school), 'member_type' => 'student', 'member_id' => Student::factory()->for($school), 'joined_on' => now()->toDateString(),
        ];
    }
}
