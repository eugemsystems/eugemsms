<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Models\BehaviourCategory;
use Modules\Welfare\Models\BehaviourRecord;

/**
 * @extends Factory<BehaviourRecord>
 */
class BehaviourRecordFactory extends Factory
{
    protected $model = BehaviourRecord::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'student_id' => Student::factory()->for($school),
            'category_id' => BehaviourCategory::factory()->create(['school_id' => $school]),
            'polarity' => 'negative',
            'points' => -3,
            'occurred_at' => now(),
            'description' => 'Did not submit homework.',
            'reported_by' => User::factory(),
            'status' => 'recorded',
            'is_confidential' => false,
        ];
    }
}
