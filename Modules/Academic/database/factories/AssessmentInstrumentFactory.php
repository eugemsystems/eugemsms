<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Core\Models\School;

/**
 * @extends Factory<AssessmentInstrument>
 */
class AssessmentInstrumentFactory extends Factory
{
    protected $model = AssessmentInstrument::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'framework_id' => CurriculumFramework::factory()->for($school),
            'code' => 'SBP',
            'name' => 'School-Based Project',
            'projects_per_subject_per_year' => 1,
            'applies_to_exam_classes' => false,
            'contributes_to_final_mark' => true,
            'default_weight_percent' => '30.00',
            'is_readonly' => false,
            'reference_circular' => 'Circular No. 9 of 2024',
            'status' => 'active',
        ];
    }
}
