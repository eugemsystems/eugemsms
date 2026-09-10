<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Models\DisciplinaryCommittee;

/**
 * @extends Factory<DisciplinaryCommittee>
 */
class DisciplinaryCommitteeFactory extends Factory
{
    protected $model = DisciplinaryCommittee::class;

    public function definition(): array
    {
        $school = School::factory();
        $chair = User::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'convened_on' => now()->toDateString(),
            'panel_staff_ids' => [1, 2, 3],
            'learner_statement' => 'The learner explained what happened.',
            'findings' => 'The panel found the account credible.',
            'decision' => 'sanction',
            'chaired_by' => $chair,
        ];
    }
}
