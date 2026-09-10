<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\InvigilationAssignment;
use Modules\Academic\Models\Venue;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * @extends Factory<InvigilationAssignment>
 */
class InvigilationAssignmentFactory extends Factory
{
    protected $model = InvigilationAssignment::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'paper_id' => ExaminationPaper::factory()->for($school),
            'venue_id' => Venue::factory()->for($school),
            'staff_id' => Staff::factory()->for($school),
            'role' => 'chief',
            'confirmed' => false,
            'report_submitted' => false,
        ];
    }
}
