<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\People\Models\Intake;

/**
 * @extends Factory<Intake>
 */
class IntakeFactory extends Factory
{
    protected $model = Intake::class;

    public function definition(): array
    {
        $school = School::factory()->create();

        return [
            'school_id' => $school->id,
            'academic_year_id' => AcademicYear::factory()->for($school)->create()->id,
            'name' => 'Form 1 Intake '.now()->year,
            'grade_level_id' => GradeLevel::factory()->for($school)->create()->id,
            'opens_on' => now()->subMonths(2)->toDateString(),
            'closes_on' => now()->addMonth()->toDateString(),
            'target_places' => 30,
            'status' => 'open',
        ];
    }
}
