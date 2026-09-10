<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMarkVersion;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<AssessmentMarkVersion>
 */
class AssessmentMarkVersionFactory extends Factory
{
    protected $model = AssessmentMarkVersion::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'assessment_id' => Assessment::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'version' => 1,
            'raw_mark' => 75,
            'percent' => 75,
            'was_published' => false,
            'changed_by' => User::factory(),
            'changed_at' => now(),
        ];
    }
}
