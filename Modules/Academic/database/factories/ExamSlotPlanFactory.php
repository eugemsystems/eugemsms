<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ExamSlotPlan;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<ExamSlotPlan>
 */
class ExamSlotPlanFactory extends Factory
{
    protected $model = ExamSlotPlan::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'name' => 'ZIMSEC Exam',
            'exam_body' => 'zimsec',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addWeeks(2)->toDateString(),
            'affected_levels' => [],
            'status' => 'draft',
            'created_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
