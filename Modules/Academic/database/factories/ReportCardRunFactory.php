<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ReportCardRun;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<ReportCardRun>
 */
class ReportCardRunFactory extends Factory
{
    protected $model = ReportCardRun::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'template_id' => 1,
            'template_version' => 1,
            'status' => 'queued',
            'requested_by' => User::factory(),
        ];
    }
}
