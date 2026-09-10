<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ExaminationSession;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<ExaminationSession>
 */
class ExaminationSessionFactory extends Factory
{
    protected $model = ExaminationSession::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'name' => 'End of Term Examinations',
            'exam_type' => 'end_of_term',
            'exam_body' => 'internal',
            'affected_levels' => [1],
            'starts_on' => now()->addWeeks(4)->toDateString(),
            'ends_on' => now()->addWeeks(5)->toDateString(),
            'index_number_pattern' => '{CENTRE}/{LEVEL}/{SEQ:4}',
            'status' => 'planning',
            'created_by' => User::factory(),
        ];
    }
}
