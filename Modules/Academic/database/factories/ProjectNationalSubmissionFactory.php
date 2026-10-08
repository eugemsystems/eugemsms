<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Academic\Models\ProjectNationalSubmission;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

/**
 * @extends Factory<ProjectNationalSubmission>
 */
class ProjectNationalSubmissionFactory extends Factory
{
    protected $model = ProjectNationalSubmission::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'instrument_id' => AssessmentInstrument::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'candidate_count' => 0,
            'exported_by' => User::factory(),
            'exported_at' => now(),
        ];
    }
}
