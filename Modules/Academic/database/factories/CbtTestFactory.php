<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Academic\Models\CbtTest;
use Modules\Academic\Models\Subject;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<CbtTest>
 */
class CbtTestFactory extends Factory
{
    protected $model = CbtTest::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'title' => 'Topic Test — Mechanics',
            'subject_id' => Subject::factory()->for($school),
            'assembly_method' => 'manual',
            'question_ids' => [],
            'randomise_question_order' => true,
            'randomise_option_order' => true,
            'duration_minutes' => 30,
            'opens_at' => Carbon::now()->subHour(),
            'closes_at' => Carbon::now()->addDay(),
            'browser_focus_monitoring' => true,
            'max_tab_switches' => 3,
            'status' => 'scheduled',
        ];
    }
}
